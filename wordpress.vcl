backend default {
  .host = "127.0.0.1";
  .port = "8080";
}

acl purge {
  "localhost";
}

sub vcl_recv {
  if (req.request == "BAN") {
    if(!client.ip ~ purge) {
      error 405 "Not allowed.";
    }
    ban("req.url ~ "+req.url+" && req.http.host == "+req.http.host);
    error 200 "Banned.";
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

sub vcl_fetch {
  if (beresp.status == 404) {
    set beresp.ttl = 0m;
    return(hit_for_pass);
  }
  if (req.url ~ "wp-(login|admin)" || req.url ~ "preview=true") {
    return (hit_for_pass);
  }

  set beresp.ttl = 24h;
  return (deliver);
}
