#include <Arduino.h>
#include <ESP8266WiFi.h>                 // <-- needed for WiFi.status() and WL_CONNECTED
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecureBearSSL.h>     // HTTPS client
#include "transmitter.h"

bool sendData(const String& node, float temp, float hum, const String& time) {
  if (WiFi.status() != WL_CONNECTED) return false;

  // Build URL (encode if you might have spaces)
  String url = "https://ninobaliro04.com/insert_sensor.php?node=" + node +
               "&temp=" + String(temp) + "&hum=" + String(hum) +
               "&time=" + time;

  std::unique_ptr<BearSSL::WiFiClientSecure> client(new BearSSL::WiFiClientSecure);
  client->setInsecure(); // skip cert validation for simplicity (OK for class project)

  HTTPClient http;
  if (!http.begin(*client, url)) return false;

  int code = http.GET();
  String payload = http.getString();
  http.end();

  Serial.printf("HTTP code: %d, payload: %s\n", code, payload.c_str());
  return (code == 200 && payload.indexOf("OK") >= 0);
}
