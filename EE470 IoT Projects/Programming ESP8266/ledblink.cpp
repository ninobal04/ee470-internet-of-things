#include <Arduino.h>
#include "ledblink.h"

Blink::Blink(int pin) {
    _pin = pin;
    pinMode(_pin, OUTPUT);
}

void Blink::blinkRate(int rate) {
    digitalWrite(_pin, LOW);
    delay(rate);
    digitalWrite(_pin, HIGH);
    delay(rate);
}
