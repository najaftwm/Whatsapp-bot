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

# Backend API URLs (update if needed)
BACKEND_URL = "http://localhost/whatsapp-backend/backendphp/api/receiveMessage.php"
# Incoming message storage endpoint (must exist on PHP side)
BACKEND_RECEIVE_URL = "http://localhost/whatsapp-backend/backendphp/api/receiveMessage.php"

# Backend API key (must match backend config)
API_KEY = "q6ktqrPs3wZ4kvZAzNdi7"

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

    # Normalize phone for WhatsApp (digits only, include country code, no '+')
    try:
        import re
        from urllib.parse import quote
        normalized_phone = re.sub(r"\D", "", phone or "")
        encoded_text = quote(message or "")
    except Exception:
        normalized_phone = (phone or "").replace('+', '')
        encoded_text = message or ""

    # Open chat using URL method (faster)
    target_url = f"https://web.whatsapp.com/send?phone={normalized_phone}&text={encoded_text}"
    drv.get(target_url)
    
    # Wait for chat to load and verify we're in the right chat
    time.sleep(4)
    
    # Verify we're still on the correct URL (not redirected to first chat)
    current_url = drv.current_url
    if normalized_phone not in current_url and 'phone=' in current_url:
        print(f"⚠ Warning: URL changed from target. Current: {current_url}")
        # Try to navigate back to correct chat
        drv.get(target_url)
        time.sleep(3)
    
    # Wait for message input box to be ready - try multiple selectors
    msg_input = None
    input_selectors = [
        '//div[@contenteditable="true"][@data-tab="10"]',  # Most common
        '//div[@contenteditable="true"][@data-tab="9"]',
        '//div[@contenteditable="true"][@role="textbox"]',
        '//footer//div[@contenteditable="true"]',
        '//div[@contenteditable="true"][contains(@class, "selectable-text")]',
    ]
    
    for selector in input_selectors:
        try:
            msg_input = WebDriverWait(drv, 3).until(
                EC.presence_of_element_located((By.XPATH, selector))
            )
            # Verify it's actually in the footer/chat area, not sidebar
            parent = msg_input.find_element(By.XPATH, './ancestor::footer | ./ancestor::div[contains(@class, "copyable-area")]')
            if parent:
                print(f"✅ Found message input with selector: {selector}")
                break
        except Exception:
            continue
    
    if not msg_input:
        print("⚠ Could not find message input box")
        return False
    
    # Focus the input and ensure message is there
    try:
        msg_input.click()
        time.sleep(0.5)
        # Check if message text is already in the input (from URL)
        current_text = msg_input.text or ""
        if message not in current_text:
            # Clear and type message
            msg_input.clear()
            msg_input.send_keys(message)
            time.sleep(0.5)
    except Exception as e:
        print(f"⚠ Could not interact with input: {e}")
        return False

    # Try multiple strategies to send the message (in order of preference)
    sent = False
    
    # Strategy 1: Click send button (most reliable)
    send_selectors = [
        '//button[@aria-label="Send"]',
        '//span[@data-icon="send"]/ancestor::button',
        '//span[@data-icon="send"]',
        '//button[contains(@class, "send")]',
    ]
    
    for selector in send_selectors:
        try:
            # Find send button near the message input
            send_btn = WebDriverWait(drv, 3).until(
                EC.element_to_be_clickable((By.XPATH, f'//div[@contenteditable="true"][@data-tab="10"]/ancestor::div[contains(@class, "copyable-area")]//{selector} | //footer//{selector}'))
            )
            send_btn.click()
            sent = True
            print("✅ Message sent successfully via send button!")
            break
        except Exception:
            continue
    
    # Strategy 2: Press Enter key in the input box
    if not sent:
        try:
            msg_input.send_keys(Keys.ENTER)
            sent = True
            print("✅ Message sent with Enter key")
        except Exception as ex:
            print(f"❌ Failed to send message: {ex}")
            return False
    
    # Wait a moment to ensure message is sent
    time.sleep(2)
    return sent


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


def _extract_current_chat_phone():
    """Extract phone number from current WhatsApp Web chat URL or header."""
    drv = init_driver()
    
    # Method 1: Extract from URL (most reliable)
    try:
        current_url = drv.current_url
        # WhatsApp Web URLs can be: /send?phone=... or chat with phone in path
        if 'phone=' in current_url:
            import re
            match = re.search(r'phone=([0-9+]+)', current_url)
            if match:
                phone = match.group(1)
                print(f"📱 Extracted phone from URL: {phone}")
                return phone
    except Exception as e:
        print(f"⚠ Could not extract phone from URL: {e}")
    
    # Method 2: Try to get phone from header title attribute (often contains phone)
    try:
        header = WebDriverWait(drv, 3).until(
            EC.presence_of_element_located((By.XPATH, '//header//span[@title]'))
        )
        title_attr = header.get_attribute("title")
        if title_attr:
            # Extract phone number from title (format: "Name\n+91...")
            import re
            # Look for phone pattern in title
            phone_match = re.search(r'(\+?[0-9]{10,15})', title_attr)
            if phone_match:
                phone = phone_match.group(1)
                print(f"📱 Extracted phone from header title: {phone}")
                return phone
    except Exception:
        pass
    
    # Method 3: Try chat info panel (if accessible)
    try:
        # Click header to open info panel
        header_btn = WebDriverWait(drv, 2).until(
            EC.element_to_be_clickable((By.XPATH, '//header//div[@role="button"]'))
        )
        header_btn.click()
        time.sleep(1)
        
        # Look for phone in info panel
        info_elements = drv.find_elements(By.XPATH, '//div[contains(@class, "copyable-text")]//span')
        for elem in info_elements:
            text = elem.text or ""
            import re
            phone_match = re.search(r'(\+?[0-9]{10,15})', text)
            if phone_match:
                phone = phone_match.group(1)
                # Close info panel
                drv.find_element(By.XPATH, '//span[@data-icon="x"]').click()
                time.sleep(0.5)
                print(f"📱 Extracted phone from info panel: {phone}")
                return phone
    except Exception:
        pass
    
    print("⚠ Could not extract phone number, returning empty")
    return ""


def _post_incoming_to_backend(phone_or_name, message_text):
    payload = {"phone": phone_or_name, "message": message_text}
    try:
        resp = requests.post(
            BACKEND_RECEIVE_URL,
            json=payload,
            headers={"x-api-key": API_KEY, "Content-Type": "application/json"},
            timeout=10,
        )
        print(f"📡 Sent to backend ({resp.status_code}): {resp.text}")
        return resp.ok
    except Exception as e:
        print(f"❌ Failed to send to backend: {e}")
        return False


def _get_last_incoming_message():
    """Return (row_id, text, phone_guess) for the latest incoming message bubble in the open chat.

    phone_guess is parsed from the row's data-id (e.g., "..._917208320766@c.us_...") when possible.
    """
    drv = init_driver()
    try:
        # Ensure latest messages are loaded and visible
        try:
            drv.find_element(By.TAG_NAME, 'body').send_keys(Keys.END)
            time.sleep(0.5)
        except Exception:
            pass

        # message-in rows contain incoming messages
        rows = drv.find_elements(By.XPATH, '//*[contains(@class, "message-in") and (self::div or self::li)]')
        if not rows:
            # Fallback: search by bubbles that have copyable-text with data-pre-plain-text
            rows = drv.find_elements(By.XPATH, '//div[contains(@class, "copyable-text") and @data-pre-plain-text]/ancestor::*[contains(@class, "message-in")]')
        if not rows:
            print("ℹ No incoming message rows found yet")
            return None
        last = rows[-1]
        # Try to get data-id from the element or any of its ancestors
        data_id = ""
        node = last
        for _ in range(6):  # climb up to a few levels just in case
            try:
                data_id = node.get_attribute("data-id") or node.get_attribute("data-message-id") or ""
                if data_id:
                    break
                node = node.find_element(By.XPATH, "..")
            except Exception:
                break
        # Build a stable row id using WhatsApp's pre-plain-text (includes timestamp/sender) + text
        pre_plain = ""
        try:
            pre_el = last.find_element(By.XPATH, './/div[contains(@class, "copyable-text")]')
            pre_plain = pre_el.get_attribute('data-pre-plain-text') or ""
        except Exception:
            pass
        # Try multiple patterns for message text
        text = ""
        patterns = [
            './/div[contains(@class, "copyable-text")]//span[contains(@class, "selectable-text")]',
            './/span[contains(@class, "selectable-text")]//span[@dir="ltr" or @dir="auto"]',
            './/div[contains(@class, "copyable-text")]//div[@role="textbox"]',
        ]
        for xp in patterns:
            try:
                el = last.find_element(By.XPATH, xp)
                text = (el.text or "").strip()
                if text:
                    break
            except Exception:
                continue
        if not text:
            # Fallback: take all text under the bubble
            text = (last.text or "").strip()
        if not text:
            print("ℹ Found incoming row but could not read text yet")
            return None
        # Compose stable id
        stable_id = f"{pre_plain}|{text}" if pre_plain else (data_id or str(hash(last)))
        # Parse phone from data-id like "false_919987464015@c.us_3EB024401BFA7A4C362654"
        phone_guess = ""
        try:
            import re
            # Match pattern: digits@c.us (e.g., 919987464015@c.us)
            m = re.search(r"(\d{10,15})@c\.us", data_id or "")
            if m:
                digits = m.group(1)
                # Prepend '+' to align with DB format (e.g., +919987464015)
                phone_guess = f"+{digits}"
        except Exception:
            pass
        return (stable_id, text, phone_guess)
    except Exception:
        return None


def start_whatsapp_incoming_monitor():
    """Start a background thread to watch for new incoming messages and forward to backend."""
    def _runner():
        print("👀 Starting WhatsApp incoming message monitor...")
        seen_last_id = None
        recent_ids = set()
        phone_last_ids = {}
        phone_recent_texts = {}
        while True:
            try:
                init_driver()
                last = _get_last_incoming_message()
                if last:
                    # Unpack with backward compatibility
                    if len(last) == 3:
                        row_id, text, phone_guess = last
                    else:
                        row_id, text = last
                        phone_guess = ""
                    # On first run, seed the last seen message to avoid auto-replying to old history
                    if seen_last_id is None:
                        seen_last_id = row_id
                        recent_ids.add(row_id)
                        continue

                    # Ignore empty/whitespace messages
                    if (text or "").strip() == "":
                        continue

                    if row_id not in recent_ids:
                        phone = phone_guess or _extract_current_chat_phone()
                        if not phone:
                            print(f"⚠ Could not extract phone number for message: {text[:50]}...")
                            # Still try to send, backend will handle it
                            phone = ""
                        # Per-phone de-dup: if this row_id is same as last for that phone, skip
                        last_for_phone = phone_last_ids.get(phone)
                        if last_for_phone == row_id:
                            continue

                        # Per-phone content window: avoid re-sending exact same content within 2 minutes
                        from time import time as _time
                        window = phone_recent_texts.get(phone, [])
                        now_ts = _time()
                        # prune entries older than 120s
                        window = [(t, s) for (t, s) in window if now_ts - t < 120]
                        if any(s == text for (t, s) in window):
                            phone_recent_texts[phone] = window
                            continue

                        print(f"🟢 New incoming message from '{phone}': {text}")
                        _post_incoming_to_backend(phone, text)
                        seen_last_id = row_id
                        recent_ids.add(row_id)
                        phone_last_ids[phone] = row_id
                        window.append((now_ts, text))
                        phone_recent_texts[phone] = window
                        # Keep only last 50 ids to avoid memory growth
                        if len(recent_ids) > 50:
                            recent_ids = set(list(recent_ids)[-50:])
            except Exception as e:
                print(f"Monitor error: {e}")
            time.sleep(2)

    t = threading.Thread(target=_runner, daemon=True)
    t.start()


if __name__ == '__main__':
    print("=" * 60)
    print("🚀 WhatsApp Bot starting...")
    print("=" * 60)
    print("⚠ IMPORTANT: Make sure script.bat is running FIRST!")
    print("   Chrome should be open with remote debugging enabled.")
    print("=" * 60)
    print("Starting Flask server on http://localhost:5000")
    print("=" * 60)
    # Start background monitor for incoming messages
    try:
        start_whatsapp_incoming_monitor()
    except Exception as e:
        print(f"⚠ Could not start incoming monitor: {e}")
    app.run(port=5000, debug=True)
