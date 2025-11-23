import subprocess
import time

# Full file paths
admin_path = r"chatbot\admin_bot.py"
customer_path = r"chatbot\customer_bot.py"

print("🚀 Starting Admin Bot on port 7000...")
admin_process = subprocess.Popen(["python", admin_path])

time.sleep(2)

print("🤖 Starting Customer Bot on port 5000...")
customer_process = subprocess.Popen(["python", customer_path])

print("\n✅ Both bots are running!")
print("   Admin: http://127.0.0.1:7000")
print("   Customer: http://127.0.0.1:5000")
print("\n🛑 Press CTRL+C to stop both.")

try:
    admin_process.wait()
    customer_process.wait()
except KeyboardInterrupt:
    print("\nStopping both bots...")    
    admin_process.terminate()
    customer_process.terminate()
    print("✅ All bots stopped.")
