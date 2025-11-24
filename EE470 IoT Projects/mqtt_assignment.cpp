#include <ESP8266WiFi.h>
#include <PubSubClient.h>

// ========= Pins =========
const int potPin    = A0;   // Potentiometer
const int buttonPin = D5;   // Switch
const int ledPin    = D2;   // LED

// ========= WiFi – CHANGE THESE =========
const char* ssid     = "Neener";
const char* wifiPass = "shutuploser";

// ========= MQTT Broker =========
const char* mqttServer = "broker.hivemq.com";  
const uint16_t mqttPort = 1883;

// ========= MQTT Topics =========
const char* potTopic     = "testtopic/temp/outTopic/040";   // Outgoing data
const char* switchTopic  = "testtopic/temp/switch/040";     // Switch publishing
const char* subTopic     = "testtopic/temp/inTopic/040";    // LED control topic
const char* ledStateTopic = "testtopic/temp/ledState/040";  // LED Status topic

WiFiClient espClient;
PubSubClient client(espClient);

// Timing
unsigned long lastPublish = 0;
const unsigned long publishInterval = 15000;  // 15 seconds

// Track last switch state
int lastButtonValue = 0;

// ========= WiFi connect =========
void connectWiFi() {
  Serial.print("Connecting to WiFi: ");
  Serial.println(ssid);

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, wifiPass);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.print("WiFi connected! IP: ");
  Serial.println(WiFi.localIP());
}

// ========= Callback for MQTT Subscription =========
void callback(char* topic, byte* payload, unsigned int length) {
  Serial.print("Message arrived on topic: ");
  Serial.println(topic);

  // Convert payload to string
  String msg;
  for (unsigned int i = 0; i < length; i++) {
    msg += (char)payload[i];
  }

  Serial.print("Payload: ");
  Serial.println(msg);

  // LED control
  if (msg == "1") {
    digitalWrite(ledPin, HIGH);
    Serial.println("LED -> ON (MQTT)");
    client.publish(ledStateTopic, "LED ON");  
  }
  else if (msg == "0") {
    digitalWrite(ledPin, LOW);
    Serial.println("LED -> OFF (MQTT)");
    client.publish(ledStateTopic, "LED OFF");   
  }
}


// ========= MQTT connect =========
void reconnectMQTT() {
  while (!client.connected()) {
    Serial.print("Connecting to MQTT... ");

    String clientId = "ESP8266Client-";
    clientId += String(ESP.getChipId(), HEX);

    if (client.connect(clientId.c_str())) {
      Serial.println("connected!");

      // Subscribe to LED control topic
      client.subscribe(subTopic);
      Serial.print("Subscribed to: ");
      Serial.println(subTopic);

    } else {
      Serial.print("failed, rc = ");
      Serial.print(client.state());
      Serial.println(" ...retrying in 5 seconds");
      delay(5000);
    }
  }
}

void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println();
  Serial.println("=== EE470 – 4.2.C MQTT Publish + Subscribe ===");

  pinMode(potPin, INPUT);
  pinMode(buttonPin, INPUT_PULLUP);
  pinMode(ledPin, OUTPUT);

  connectWiFi();

  client.setServer(mqttServer, mqttPort);
  client.setCallback(callback);

  reconnectMQTT();
}

void loop() {
  if (!client.connected()) {
    reconnectMQTT();
  }
  client.loop();

  // ----- Read potentiometer -----
  int potRaw = analogRead(potPin);

  // Publish pot value every 15s
  unsigned long now = millis();
  if (now - lastPublish >= publishInterval) {
    lastPublish = now;

    char payload[16];
    snprintf(payload, sizeof(payload), "%d", potRaw);

    Serial.print("Publishing pot value: ");
    Serial.println(payload);

    client.publish(potTopic, payload);
  }

  // ----- Switch handling: publish when state changes -----
  int raw = digitalRead(buttonPin);
  int buttonValue = (raw == LOW) ? 1 : 0;  // pressed = 1

  if (buttonValue != lastButtonValue) {
    lastButtonValue = buttonValue;

    char payload[2];
    payload[0] = buttonValue ? '1' : '0';
    payload[1] = '\0';

    Serial.print("Switch changed -> publishing ");
    Serial.println(payload);

    client.publish(switchTopic, payload);
  }

  delay(50);
}
