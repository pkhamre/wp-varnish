backend default {
  .host = "127.0.0.1";
  .port = "8080";
  .connect_timeout = 300s;
  .first_byte_timeout = 300s;
  .between_bytes_timeout = 300s;
}

acl purge {
  "localhost";
  "127.0.0.1";
}

### WordPress-specific config ###
# This config was initially derived from the work of Donncha Ó Caoimh:
# http://ocaoimh.ie/2011/08/09/speed-up-wordpress-with-apache-and-varnish/
sub vcl_recv {
  if (req.request == "BAN" || req.request == "PURGE") {
    if(!client.ip ~ purge) {
      error 405 "Not allowed.";
    }
    ban("req.url ~ "+req.url+" && req.http.host == "+req.http.host);
    error 200 "Banned.";
  }

  # pipe on weird http methods
  if (req.request !~ "^GET|HEAD|PUT|POST|TRACE|OPTIONS|DELETE$") {
    return(pipe);
  }

  ### Check for reasons to bypass the cache!
  # never cache anything except GET/HEAD
  if (req.request != "GET" && req.request != "HEAD") {
    return(pass);
  }

  # don't cache logged-in users or authors
  if (req.http.Cookie ~ "wp-postpass_|wordpress_logged_in_|comment_author|PHPSESSID") {
    return(pass);
  }

  # don't cache preview cache WP/GF
  if (req.url ~ "preview=true" || req.url ~ "gf_page=preview") {
    return (pass);
  }

  # don't cache ajax requests
  if (req.http.X-Requested-With == "XMLHttpRequest") {
    return(pass);
  }

  # don't cache these special pages
  if (req.url ~ "nocache|wp-admin|wp-(comments-post|login|activate|mail)\.php|bb-admin|server-status|control\.php|bb-login\.php|bb-reset-password\.php|register\.php") {
    return(pass);
  }

  ### looks like we might actually cache it!
  # fix up the request
  set req.grace = 2m;
  set req.url = regsub(req.url, "\?replytocom=.*$", "");

  # Remove has_js, Google Analytics __*, and wooTracker cookies.
  set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(__[a-z]+|has_js|wooTracker)=[^;]*", "");
  set req.http.Cookie = regsub(req.http.Cookie, "^;\s*", "");
  if (req.http.Cookie ~ "^\s*$") {
    unset req.http.Cookie;
  }

  return (lookup);
}

sub vcl_hash {
  # Add the browser cookie only if a WordPress cookie found.
  if (req.http.Cookie ~ "wp-postpass_|wordpress_logged_in_|comment_author|PHPSESSID") {
    hash_data(req.http.Cookie);
  }
}

sub vcl_fetch {
  # Uncomment to make the default cache "time to live" is 24 hours, handy 
  # but it may cache stale pages unless purged.
  # By default Varnish will use the headers sent to it by Apache (the backend server)
  # to figure out the correct TTL. 
  set beresp.ttl = 24h;
 
  # make sure grace is at least 2 minutes
  if (beresp.grace < 2m) {
    set beresp.grace = 2m;
  }
  
  # catch obvious reasons we can't cache
  if (beresp.http.Set-Cookie) {
    set beresp.ttl = 0s;
  }
  
  # Varnish determined the object was not cacheable
  if (beresp.ttl <= 0s) {
    set beresp.http.X-Cacheable = "NO:Not Cacheable";
    return(hit_for_pass);
  # You don't wish to cache content for logged in users
  } else if (req.http.Cookie ~ "wp-postpass_|wordpress_logged_in_|comment_author|PHPSESSID") {
    set beresp.http.X-Cacheable = "NO:Got Session";
    return(hit_for_pass);
  # You are respecting the Cache-Control=private header from the backend
  } else if (beresp.http.Cache-Control ~ "private") {
    set beresp.http.X-Cacheable = "NO:Cache-Control=private";
    return(hit_for_pass);
  # You are extending the lifetime of the object artificially
  } else if (beresp.ttl < 300s) {
    set beresp.ttl   = 300s;
    set beresp.grace = 300s;
    set beresp.http.X-Cacheable = "YES:Forced";
  # Varnish determined the object was cacheable
  } else {
    set beresp.http.X-Cacheable = "YES";
  }
  
  # Avoid caching error responses
  if (beresp.status == 404 || beresp.status >= 500) {
    set beresp.ttl   = 0s;
    set beresp.grace = 15s;
  }
  
  # Deliver the content
  return(deliver);
}

#Comment this out if you don't want to see weather there was a HIT or MISS in the headers
sub vcl_deliver {
        if (obj.hits > 0) {
                set resp.http.X-Cache = "HIT";
        } else {
                set resp.http.X-Cache = "MISS";
        }
}
