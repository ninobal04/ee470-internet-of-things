import paho.mqtt.client as mqtt
import mysql.connector

# ========= MQTT settings =========
BROKER_URL  = "broker.hivemq.com"
BROKER_PORT = 1883                     
TOPIC       = "testtopic/temp/outTopic/040" 

# ========= Database settings (from Hostinger) =========
HOST     = "srv1918.hstgr.io"    
USER     = "u383530867_nbal"
PASSWORD = "Billydaman040!"
DATABASE = "u383530867_mqtt"

# ========= Function to push one value into DB =========
def push_value_to_db(sensor_value: int):
    try:
        # Connect to the database
        connection = mysql.connector.connect(
            host=HOST,
            user=USER,
            password=PASSWORD,
            database=DATABASE
        )

        if connection.is_connected():
            print("Connected to database")

            cursor = connection.cursor()

            # If your table/column names differ, change this query
            insert_query = "INSERT INTO sensor_value (value) VALUES (%s)"
            cursor.execute(insert_query, (sensor_value,))

            connection.commit()
            print(f"Inserted {sensor_value} into sensor_value table")

    except mysql.connector.Error as err:
        print(f"Database error: {err}")

    finally:
        try:
            if connection.is_connected():
                cursor.close()
                connection.close()
                print("Database connection closed")
        except NameError:
            # connection was never created successfully
            pass

# ========= MQTT callbacks =========
def on_connect(client, userdata, flags, rc):
    if rc == 0:
        print("Connected to HiveMQ!")
        client.subscribe(TOPIC)
        print(f"Subscribed to: {TOPIC}")
    else:
        print(f"Failed to connect, return code {rc}")

def on_message(client, userdata, msg):
    payload = msg.payload.decode().strip()
    print(f"Received message '{payload}' on topic {msg.topic}")

    # Try to convert payload from ESP to an integer
    try:
        value = int(payload)
        push_value_to_db(value)
    except ValueError:
        print(f"Could not convert payload '{payload}' to int, ignoring.")

# ========= Main =========
def main():
    client = mqtt.Client()  

    client.on_connect = on_connect
    client.on_message = on_message

    print("Connecting to broker...")
    client.connect(BROKER_URL, BROKER_PORT, 60)

    # Blocking loop – keep script running and listening
    client.loop_forever()

if __name__ == "__main__":
    main()
