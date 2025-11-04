/*
 * ----------------------------------------------
 * Project/Program Name : ESP8266 LED Blink
 * File Name            : ledblink.h
 * Author               : Antonino Balistreri
 * Date                 : 11/03/2025
 * Version              : 1.0
 *
 * Purpose:
 *   Header file for Blink class to blink an LED.
 * Inputs:
 *   pin number connected to LED
 * Outputs:
 *   LED blinking at specified rate
 * Dependencies:
 *   ESP8266WiFi, Arduino framework
 * ----------------------------------------------
 */
#ifndef LEDBLINK_H
#define LEDBLINK_H

class Blink {
public:
    Blink(int pin);
    void blinkRate(int rate);

private:
    int _pin;
};

#endif
