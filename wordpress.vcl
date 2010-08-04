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

    purge("req.url ~ ^" req.url "$ && req.http.host == "req.http.host);
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

  if (req.url ~ "wp-(login|admin)") {
    return (pass);
  }

  remove req.http.cookie;
  return (lookup);
}

sub vcl_fetch {
  if (req.url ~ "wp-(login|admin)") {
    return (pass);
  }

  set obj.ttl = 24h;
  return (deliver);
}
