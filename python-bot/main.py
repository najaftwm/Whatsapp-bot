from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.common.action_chains import ActionChains
from urllib.parse import quote_plus
import os
import sys
import winsound
import time

os.system("script.bat")
opt = Options()
opt.add_experimental_option("debuggerAddress","localhost:8989")

driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()), options=opt)
wait = WebDriverWait(driver, 600)

# Navigate to WhatsApp Web main page
driver.get("https://web.whatsapp.com/")

winsound.Beep(700, 800)

# Wait for the page to load
time.sleep(5)

# Phone number to search for
search_number = "9892026250"
message = "Hello, We will reach out to you within 12 hours"
last_digits = search_number[-4:]

chat_selected = False
print(f"Searching for number: {search_number}")

# Find the search input field (contenteditable div with aria-placeholder)
search_input = wait.until(EC.element_to_be_clickable((By.CSS_SELECTOR, 'div[contenteditable="true"][aria-placeholder*="Search"]')))
search_input.click()
time.sleep(1)

# Clear any existing text
driver.execute_script("arguments[0].innerText = '';", search_input)
driver.execute_script("arguments[0].textContent = '';", search_input)

# Type the phone number using send_keys for better search functionality
search_input.send_keys(Keys.CONTROL + "a")  # Select all
search_input.send_keys(Keys.DELETE)  # Delete
search_input.send_keys(search_number)

print(f"Typed number: {search_number}")
time.sleep(2)  # Wait for search results to appear

# PRIMARY METHOD: Use keyboard navigation (most reliable for WhatsApp Web)
# This is what users do manually - press ARROW_DOWN to select first result, then ENTER to open
print("Opening chat using keyboard navigation (ARROW_DOWN + ENTER)...")
try:
    # Ensure search input is focused
    search_input.click()
    time.sleep(0.3)
    
    # Press ARROW_DOWN to select the first search result
    search_input.send_keys(Keys.ARROW_DOWN)
    print("✓ Selected first search result")
    time.sleep(0.5)
    
    # Press ENTER to open the selected chat
    search_input.send_keys(Keys.ENTER)
    print("✓ Pressed ENTER to open chat")
    time.sleep(2)  # Wait for chat to open
    
    # Verify chat opened by checking for message input field
    try:
        WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.CSS_SELECTOR, 
                'footer div[contenteditable="true"], '
                'div[contenteditable="true"][role="textbox"], '
                'div[contenteditable="true"][data-tab="10"]'))
        )
        chat_selected = True
        print("✓ Chat opened successfully using keyboard navigation!")
    except Exception as e:
        print(f"Chat verification failed: {e}")
        # Try one more time with longer wait
        time.sleep(2)
        try:
            WebDriverWait(driver, 5).until(
                EC.presence_of_element_located((By.CSS_SELECTOR, 'footer div[contenteditable="true"]'))
            )
            chat_selected = True
            print("✓ Chat opened successfully (retry verification)")
        except:
            print("Chat verification failed even after retry")

except Exception as e:
    print(f"Keyboard navigation failed: {e}")
    print("Trying alternative methods...")

# FALLBACK METHOD: Try clicking the first search result
if not chat_selected:
    print("Trying click-based method...")
    try:
        # Wait for search results to appear
        chat_rows = WebDriverWait(driver, 10).until(
            EC.presence_of_all_elements_located((By.CSS_SELECTOR, 'div[role="row"]'))
        )
        if chat_rows:
            chat_row = chat_rows[0]
            print("Found first search result row")
            
            # Find clickable element
            try:
                clickable_element = chat_row.find_element(By.CSS_SELECTOR, 'div[role="gridcell"][tabindex="0"]')
            except:
                clickable_element = chat_row
            
            # Scroll and click
            driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", clickable_element)
            time.sleep(0.5)
            driver.execute_script("arguments[0].click();", clickable_element)
            print("✓ Clicked on search result")
            time.sleep(2)
            
            # Verify chat opened
            WebDriverWait(driver, 10).until(
                EC.presence_of_element_located((By.CSS_SELECTOR, 'footer div[contenteditable="true"]'))
            )
            chat_selected = True
            print("✓ Chat opened using click method")
    except Exception as e:
        print(f"Click method failed: {e}")

if not chat_selected:
    print("ERROR: Could not select chat. Please check if the number exists or try again.")
    print("Tip: Make sure the number is correct and WhatsApp Web is fully loaded.")
    driver.close()
    sys.exit(1)

# Wait for chat to fully open
print("Waiting for chat to open...")
time.sleep(3)

# Find the message input field - try multiple selectors
print("Looking for message input field...")
message_input = None
selectors = [
    'div[contenteditable="true"][data-tab="10"]',
    'div[contenteditable="true"][role="textbox"]',
    'footer div[contenteditable="true"]',
    'div[contenteditable="true"][aria-label*="message"]',
    'div[contenteditable="true"][aria-label*="Type"]',
    'div[contenteditable="true"].selectable-text',
]

for selector in selectors:
    try:
        message_input = wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, selector)))
        print(f"Found message input using selector: {selector}")
        break
    except:
        continue

if not message_input:
    print("ERROR: Could not find message input field.")
    print("Attempting to find any contenteditable element in footer...")
    try:
        footer = driver.find_element(By.CSS_SELECTOR, 'footer')
        message_input = footer.find_element(By.CSS_SELECTOR, 'div[contenteditable="true"]')
        print("Found message input in footer")
    except:
        print("ERROR: Chat might not be opened properly.")
        driver.close()
        sys.exit(1)

# Scroll message input into view
driver.execute_script("arguments[0].scrollIntoView(true);", message_input)
time.sleep(1)

# Click on the message input to focus it
try:
    message_input.click()
except:
    driver.execute_script("arguments[0].click();", message_input)
time.sleep(1)

# Clear any existing text using JavaScript
driver.execute_script("arguments[0].innerText = '';", message_input)
driver.execute_script("arguments[0].textContent = '';", message_input)
time.sleep(0.5)

# Type the message character by character (more reliable for WhatsApp)
print(f"Typing message: {message}")
for char in message:
    message_input.send_keys(char)
    time.sleep(0.1)  # Small delay between characters

time.sleep(1)

# Verify message was typed
try:
    current_text = driver.execute_script("return arguments[0].innerText || arguments[0].textContent;", message_input)
    print(f"Current text in input: '{current_text}'")
except:
    print("Could not verify text in input")

# Wait a bit for WhatsApp to process the text and enable send button
print("Waiting for WhatsApp to process message and enable send button...")
time.sleep(2)

# Try to send the message using multiple methods
print("Attempting to send message...")
message_sent = False

# Method 1: Wait for send button to be enabled and click it (most reliable)
try:
    # Wait for send button to be visible and enabled
    print("Looking for send button...")
    send_button = None
    
    # Try multiple selectors with wait
    send_selectors = [
        # New WA icon and role-based button
        '//span[@data-icon="wds-ic-send-filled"]/ancestor::*[self::button or @role="button"][not(@disabled)]',
        '//*[@role="button" and @aria-label="Send" and not(@disabled)]',
        # Legacy icons/selectors
        '//span[@data-icon="send"]/ancestor::*[self::button or @role="button"][not(@disabled)]',
        '//span[@data-icon="send-light"]/ancestor::*[self::button or @role="button"][not(@disabled)]',
        '//button[@aria-label="Send"][not(@disabled)]',
        '//button[@aria-label="Send message"][not(@disabled)]',
    ]
    
    for selector in send_selectors:
        try:
            send_button = wait.until(EC.element_to_be_clickable((By.XPATH, selector)))
            print(f"Found send button using: {selector}")
            break
        except:
            continue
    
    if send_button:
        # Scroll button into view
        driver.execute_script("arguments[0].scrollIntoView({block: 'center', behavior: 'smooth'});", send_button)
        time.sleep(0.5)
        
        # Check if button is enabled
        is_enabled = driver.execute_script("return !arguments[0].disabled && arguments[0].offsetParent !== null;", send_button)
        if is_enabled:
            # Use JavaScript click for more reliability
            driver.execute_script("arguments[0].click();", send_button)
            print("✓ Clicked Send button using JavaScript")
            time.sleep(2)
            message_sent = True
        else:
            print("Send button found but not enabled")
    else:
        print("Could not find send button")
        
except Exception as e:
    print(f"Send button method failed: {e}")

# Method 2: Try pressing Enter key
if not message_sent:
    try:
        print("Trying Enter key method...")
        message_input.send_keys(Keys.RETURN)
        print("✓ Pressed Enter key")
        time.sleep(2)
        message_sent = True
    except Exception as e:
        print(f"Enter key failed: {e}")

# Method 3: Try JavaScript to find and click send button
if not message_sent:
    try:
        print("Trying JavaScript send method...")
        result = driver.execute_script("""
            // Try multiple ways to find send button
            function isClickable(el){
                if (!el) return false;
                if (el.disabled) return false;
                var style = window.getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden' || el.offsetParent === null) return false;
                return true;
            }

            // New icon
            var sendIconNew = document.querySelector('span[data-icon="wds-ic-send-filled"]');
            if (sendIconNew){
                var btnNew = sendIconNew.closest('button, [role="button"]');
                if (isClickable(btnNew)) { btnNew.click(); return true; }
            }

            // Legacy icons
            var sendIcon = document.querySelector('span[data-icon="send"], span[data-icon="send-light"]');
            if (sendIcon) {
                var btn = sendIcon.closest('button, [role="button"]');
                if (isClickable(btn)) { btn.click(); return true; }
            }
            
            // ARIA buttons
            var ariaBtns = document.querySelectorAll('button[aria-label="Send"], button[aria-label="Send message"], [role="button"][aria-label="Send"]');
            for (var i = 0; i < ariaBtns.length; i++) {
                if (isClickable(ariaBtns[i])) { ariaBtns[i].click(); return true; }
            }
            return false;
        """)
        if result:
            print("✓ Sent using JavaScript")
            time.sleep(2)
            message_sent = True
        else:
            print("JavaScript could not find enabled send button")
    except Exception as e:
        print(f"JavaScript send failed: {e}")

# Method 4: Try pressing Enter again with re-focus
if not message_sent:
    try:
        print("Trying Enter key with re-focus...")
        message_input.click()
        time.sleep(0.5)
        message_input.send_keys(Keys.RETURN)
        print("✓ Sent using Enter key (final attempt)")
        time.sleep(2)
        message_sent = True
    except Exception as e:
        print(f"Final send attempt failed: {e}")

# Verify if message was actually sent by checking if input is cleared
if message_sent:
    time.sleep(1)  # Wait a moment for WhatsApp to process
    try:
        remaining_text = driver.execute_script("return arguments[0].innerText || arguments[0].textContent || '';", message_input)
        if remaining_text.strip() == "" or remaining_text.strip() != message:
            print("\n✓✓✓ SUCCESS: Message appears to have been sent! ✓✓✓")
            print("(Input field was cleared after sending)")
        else:
            print(f"\n⚠ Input field still contains: '{remaining_text}'")
            print("Message might not have been sent. Please check manually.")
    except:
        print("\n✓✓✓ Message sending attempted successfully! ✓✓✓")
        print("Please verify in WhatsApp that the message was sent.")
else:
    print("\n⚠⚠⚠ WARNING: Could not confirm message was sent. ⚠⚠⚠")
    print("The message might still be in the input field. Please check manually.")
    
    # Show what's in the input field
    try:
        remaining_text = driver.execute_script("return arguments[0].innerText || arguments[0].textContent || '';", message_input)
        print(f"Current input field content: '{remaining_text}'")
    except:
        pass

winsound.Beep(700, 800)  # Beep when done

print("\nScript completed. Browser will close in 3 seconds...")
time.sleep(3)
driver.close()
