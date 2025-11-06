from flask import Flask, request, jsonify
from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from webdriver_manager.chrome import ChromeDriverManager
import time
import threading
import requests
import sys
import winsound

app = Flask(__name__)

# Backend API URL (update if needed)
BACKEND_URL = "http://localhost/whatsapp-backend/backendphp/api/receiveMessage.php"

driver = None
wait = None

def init_driver():
    """Attach to existing Chrome debug session or start new."""
    global driver, wait
    if driver is None:
        print("Connecting to Chrome via remote debugging...")
        max_retries = 5
        for attempt in range(max_retries):
            try:
                opt = Options()
                opt.add_experimental_option("debuggerAddress", "localhost:8989")
                driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()), options=opt)
                wait = WebDriverWait(driver, 30)
                
                # Check if Chrome is responsive
                driver.current_url
                print("✅ Connected to Chrome")
                
                # Navigate to WhatsApp Web if not already there
                current_url = driver.current_url
                if "web.whatsapp.com" not in current_url:
                    print("Navigating to WhatsApp Web...")
                    driver.get("https://web.whatsapp.com/")
                    time.sleep(5)
                else:
                    print("WhatsApp Web already loaded")
                
                print("✅ Ready to send/receive messages")
                winsound.Beep(700, 300)
                return driver
            except Exception as e:
                if attempt < max_retries - 1:
                    print(f"⚠ Attempt {attempt + 1} failed: {e}. Retrying in 2 seconds...")
                    time.sleep(2)
                else:
                    print(f"❌ Failed to connect to Chrome after {max_retries} attempts")
                    print("Make sure script.bat is running and Chrome is open with debugging enabled")
                    raise
    return driver


def send_whatsapp_message(phone, message):
    """Send a message to a specific WhatsApp number."""
    drv = init_driver()
    print(f"Sending message to {phone}: {message}")

    # Open chat using URL method (faster)
    drv.get(f"https://web.whatsapp.com/send?phone={phone}&text={message}")
    time.sleep(5)

    try:
        # Try send button first
        send_button = WebDriverWait(drv, 10).until(
            EC.element_to_be_clickable((By.XPATH, '//span[@data-icon="send"]'))
        )
        send_button.click()
        print("✅ Message sent successfully!")
    except Exception as e:
        print(f"⚠ Send button not found, trying Enter key... {e}")
        try:
            msg_box = WebDriverWait(drv, 10).until(
                EC.presence_of_element_located((By.XPATH, '//div[@contenteditable="true"][@data-tab="10"]'))
            )
            msg_box.send_keys(Keys.ENTER)
            print("✅ Message sent with Enter key")
        except Exception as ex:
            print(f"❌ Failed to send message: {ex}")
            return False
    time.sleep(3)
    return True


@app.route('/send_message', methods=['POST'])
def send_message():
    """Receive send request from backend."""
    data = request.get_json(force=True)
    phone = data.get("phone_number")
    message = data.get("message")
    if not phone or not message:
        return jsonify({"ok": False, "error": "phone_number and message required"}), 400

    success = send_whatsapp_message(phone, message)
    return jsonify({"ok": success})


@app.route('/receive_message', methods=['POST'])
def receive_message():
    """
    Simulate a message received from a client.
    Bot will auto-reply via WhatsApp and update backend.
    """
    data = request.get_json(force=True)
    phone = data.get("phone_number")
    message = data.get("message")

    if not phone or not message:
        return jsonify({"ok": False, "error": "phone_number and message required"}), 400

    print(f"📩 Incoming message from {phone}: {message}")

    # Notify backend to save this message
    try:
        resp = requests.post(
            BACKEND_URL,
            json={"phone_number": phone, "message": message},
            timeout=10,
        )
        print(f"➡ Backend response: {resp.text}")
    except Exception as e:
        print(f"⚠ Failed to notify backend: {e}")

    # Auto reply
    reply_text = "Hello, We will reach out to you within 12 hours."
    send_whatsapp_message(phone, reply_text)

    return jsonify({"ok": True, "auto_reply": reply_text})


if __name__ == '__main__':
    print("=" * 60)
    print("🚀 WhatsApp Bot starting...")
    print("=" * 60)
    print("⚠ IMPORTANT: Make sure script.bat is running FIRST!")
    print("   Chrome should be open with remote debugging enabled.")
    print("=" * 60)
    print("Starting Flask server on http://localhost:5000")
    print("=" * 60)
    app.run(port=5000, debug=True)
