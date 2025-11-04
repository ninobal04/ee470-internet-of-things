#include <Arduino.h>
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecureBearSSL.h>
#include "DHT.h"

#define DHTPIN      D5
#define DHTTYPE     DHT11
#define BUTTON_PIN  D7   // HW-483: S->D7, +->3V3, -/GND->G (usually HIGH when pressed)
#define TILT_PIN    D6   // HW-505: S->D6, +->3V3, -/GND->G (choose HIGH/LOW below)

const char* SSID = "Neener";
const char* PASS = "shutuploser";
static const char* HOST = "ninobaliro04.com"; 

DHT dht(DHTPIN, DHTTYPE);

bool sendDataHTTPS(const String& node, float temp, float hum, const String& isoTime) {
  if (WiFi.status() != WL_CONNECTED) return false;

  String path = "/insert_sensor.php?node=" + node +
                "&temp=" + String(temp) +
                "&hum="  + String(hum)  +
                "&time=" + isoTime;

  std::unique_ptr<BearSSL::WiFiClientSecure> client(new BearSSL::WiFiClientSecure);
  client->setInsecure();
  client->setTimeout(15000);

  HTTPClient http;
  http.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS);
  http.setTimeout(15000);

  if (!http.begin(*client, HOST, 443, path, true)) return false;

  int code = http.GET();
  String payload = http.getString();
  http.end();

  Serial.printf("HTTP code: %d (%s)\n", code, HTTPClient::errorToString(code).c_str());
  Serial.println("payload: " + payload);
  return (code == 200 && payload.indexOf("OK") >= 0);
}

// Small, simple time fetch (HTTPS first, then TEMP HTTP fallback to keep testing)
String fetchIsoTime(const String& tz = "Los_Angeles") {
  {
    std::unique_ptr<BearSSL::WiFiClientSecure> sclient(new BearSSL::WiFiClientSecure);
    sclient->setInsecure();
    sclient->setTimeout(12000);
    HTTPClient http; http.setTimeout(12000); http.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS);
    String url = "https://timeapi.io/api/Time/current/zone?timeZone=America/" + tz;
    if (http.begin(*sclient, url)) {
      int code = http.GET();
      if (code == 200) {
        String body = http.getString(); http.end();
        int p = body.indexOf("\"dateTime\":\""); if (p >= 0) { p += 12; int q = body.indexOf("\"", p); if (q > p) return body.substring(p, q); }
      } else { http.end(); }
    }
  }
  {
    std::unique_ptr<BearSSL::WiFiClientSecure> sclient(new BearSSL::WiFiClientSecure);
    sclient->setInsecure();
    sclient->setTimeout(12000);
    HTTPClient http; http.setTimeout(12000); http.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS);
    String url = "https://www.timeapi.io/api/Time/current/zone?timeZone=America/" + tz;
    if (http.begin(*sclient, url)) {
      int code = http.GET();
      if (code == 200) {
        String body = http.getString(); http.end();
        int p = body.indexOf("\"dateTime\":\""); if (p >= 0) { p += 12; int q = body.indexOf("\"", p); if (q > p) return body.substring(p, q); }
      } else { http.end(); }
    }
  }
  // TEMP fallback (HTTP) — comment out for final if your instructor forbids it.
  {
    WiFiClient client; HTTPClient http; http.setTimeout(10000);
    String url = "http://worldtimeapi.org/api/timezone/America/" + tz;
    if (http.begin(client, url)) {
      int code = http.GET();
      if (code == 200) {
        String body = http.getString(); http.end();
        int p = body.indexOf("\"datetime\":\"");
        if (p >= 0) { p += 12; int q = body.indexOf("\"", p); if (q > p) {
            String dt = body.substring(p, q);
            int dot = dt.indexOf('.'); if (dot > 0) dt = dt.substring(0, dot);
            return dt;
        } }
      } else { http.end(); }
    }
  }
  return "";
}

bool wasButton=false, wasTilt=false;
uint32_t lastSendMs = 0;

void setup() {
  Serial.begin(115200);
  dht.begin();
  pinMode(BUTTON_PIN, INPUT);
  pinMode(TILT_PIN,   INPUT);

  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(SSID, PASS);
  Serial.print("Connecting");
  while (WiFi.status() != WL_CONNECTED) { Serial.print("."); delay(500); }
  Serial.printf("\nWiFi connected: %s\n", WiFi.localIP().toString().c_str());
  Serial.printf("ESP8266 MAC Address: %s\n", WiFi.macAddress().c_str());
}

void loop() {
  int b = digitalRead(BUTTON_PIN);
  int t = digitalRead(TILT_PIN);

  bool buttonPressed = (b == HIGH);        
  bool tilted        = (t == HIGH);       

  bool pressEdge = (buttonPressed && !wasButton);
  bool tiltEdge  = (tilted        && !wasTilt);
  wasButton = buttonPressed;
  wasTilt   = tilted;

  if ((pressEdge || tiltEdge) && millis() - lastSendMs > 800) {
    float temp = dht.readTemperature();
    float hum  = dht.readHumidity();
    if (isnan(temp) || isnan(hum)) { Serial.println("DHT read failed"); delay(800); return; }

    String iso = fetchIsoTime("Los_Angeles");
    if (!iso.length()) { Serial.println("Time fetch failed; not sending."); delay(800); return; }

    String node = pressEdge ? "node_1" : "node_2";
    Serial.printf("Sending %s T=%.1f H=%.1f time=%s\n", node.c_str(), temp, hum, iso.c_str());
    bool ok = sendDataHTTPS(node, temp, hum, iso);
    Serial.printf("Result => %s\n", ok ? "OK" : "FAIL");
    lastSendMs = millis();
  }
  delay(20);
}
