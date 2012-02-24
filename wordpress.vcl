backend default {
  .host = "127.0.0.1";
  .port = "8080";
}

acl purge {
  "localhost";
}

sub vcl_recv {
  if (req.request == "PURGE") {
    if(!client.ip ~ purge) {
      error 405 "Not allowed.";
    }
    return(lookup);
  }

  if (req.request != "GET" &&
      req.request != "HEAD" &&
      req.request != "PUT" &&
      req.request != "POST" &&
      req.request != "TRACE" &&
      req.request != "OPTIONS" &&
      req.request != "DELETE") {
    return (pipe);
  }

  if (req.request != "GET" && req.request != "HEAD") {
    return (pass);
  }

  if (req.url ~ "wp-(login|admin)" || req.url ~ "preview=true") {
    return (pass);
  }

  remove req.http.cookie;
  return (lookup);
}

sub vcl_hit {
  if (req.request == "PURGE") {
    purge;
    error 200 "Purged.";
  }
}

sub vcl_miss {
  if (req.request == "PURGE") {
    purge;
    error 200 "Purged.";
  }
}

sub vcl_fetch {
  if (req.url ~ "wp-(login|admin)" || req.url ~ "preview=true") {
    return (hit_for_pass);
  }

  set beresp.ttl = 24h;
  return (deliver);
}
