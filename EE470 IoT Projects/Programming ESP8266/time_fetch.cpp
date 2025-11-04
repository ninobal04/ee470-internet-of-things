#include "time_fetch.h"
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecureBearSSL.h>

String getCurrentTime(const String& tz) {
  std::unique_ptr<BearSSL::WiFiClientSecure> client(new BearSSL::WiFiClientSecure);
  client->setInsecure();  // skip SSL cert validation

  HTTPClient http;
  String url = "https://timeapi.io/api/Time/current/zone?timeZone=America/" + tz;
  http.begin(*client, url);
  int code = http.GET();
  String result = "";
  if (code == HTTP_CODE_OK) {
    result = http.getString();
  }
  http.end();
  return result;  // JSON string — can parse later
}
