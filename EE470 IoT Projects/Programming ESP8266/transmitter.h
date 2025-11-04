#ifndef TRANSMITTER_H
#define TRANSMITTER_H
#include <Arduino.h>

bool sendData(const String& node, float temp, float hum, const String& time);

#endif
