import imaplib
import email
import os
import chardet  # pip install chardet
import MySQLdb  # pip install mysqlclient
from dateutil.parser import parse # pip install python-dateutil
import configparser
import datetime
import pytz  # pip install pytz
from email.header import decode_header

def get_last_run_timestamp():  # Get the last date and time that emailmonitor.py was executed
    try:
        # Seek the last line of emailmonitorlog.txt to find the last execution date
        with open('emailmonitorlog.txt', 'rb') as f:
            try:  # catch OSError in case of a one line file 
                f.seek(-2, os.SEEK_END)
                while f.read(1) != b'\n':
                    f.seek(-2, os.SEEK_CUR)
            except OSError:
                f.seek(0)
            timestamp_str = f.readline().decode()
        if timestamp_str == '': return None
        parsed_datetime = parse(timestamp_str)
        formatted_date = parsed_datetime.strftime("%d-%b-%Y")
        return formatted_date
    except FileNotFoundError:
        return None
    
def save_current_timestamp():  # Save the current date and time to the emailmonitorlog.txt after exectuting
    current_timestamp = datetime.datetime.now()
    timestamp_str = current_timestamp.strftime("%m-%d-%Y %H:%M:%S")
    # Append the current timestamp to the log file
    try:
        with open("emailmonitorlog.txt", "a") as log_file:
            log_file.write(timestamp_str + "\n")
    except FileNotFoundError:
        print("emailmonitorlog.txt missing")

def get_email_body(email_message):
    def get_charset():
        charset = email_message.get_charset()
        return charset if charset else 'utf-8'

    if email_message.is_multipart():
        for part in email_message.walk():
            content_type = part.get_content_type()
            charset = get_charset()

            if "text/plain" in content_type:
                try:
                    email_body = part.get_payload(decode=True).decode(charset)
                    break
                except UnicodeDecodeError:
                    # If decoding fails, use chardet to detect the character encoding
                    detected_charset = chardet.detect(part.get_payload(decode=True))['encoding']
                    email_body = part.get_payload(decode=True).decode(detected_charset)
                    break
        else:
            email_body = ""  # If no plain text part is found
    else:
        charset = get_charset()
        try:
            email_body = email_message.get_payload(decode=True).decode(charset)
        except UnicodeDecodeError:
            # If decoding fails, use chardet to detect the character encoding
            detected_charset = chardet.detect(email_message.get_payload(decode=True))['encoding']
            email_body = email_message.get_payload(decode=True).decode(detected_charset)

    return email_body

config = configparser.ConfigParser()
config.read("config.ini")

email_address = config.get("credentials", "username")
password = config.get("credentials", "password")
imap_server = config.get("credentials", "host")
mailbox = "INBOX"
# Connect to inbox

db_config = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": config.get("database", "database")
}

try:
    conn = MySQLdb.connect(**db_config)
except MySQLdb.OperationalError:
    print("Can't connect to the database server")
    exit(1)

cursor = conn.cursor()

mail = imaplib.IMAP4_SSL(imap_server)
mail.login(email_address, password)
mail.select(mailbox)

from_date = get_last_run_timestamp()
if from_date is None:
    from_date = "01-Jan-1970"


print("Saving emails received after:", from_date)
status, uids = mail.uid("search", None, f'SINCE "{from_date}"')

count = 0
est_timezone = pytz.timezone("America/New_York")
for uid in uids[0].split():

    status, msg_data = mail.uid("fetch", uid, "(RFC822)")
    raw_email = msg_data[0][1]
    email_message = email.message_from_bytes(raw_email)

    # Extract necessary details like email address, subject, date, etc.
    email_id = email_message["Message-ID"]
    email_subject = email_message["subject"]
    email_from = email_message["from"]
    email_date = email_message["date"]
    start_bracket = email_from.find("<")
    end_bracket = email_from.find(">")
    sendername = email_from[:start_bracket - 1]
    senderaddr = email_from[start_bracket + 1:end_bracket]


    # Parse date into SQL datetime format and EST timezone
    if email_date is not None:
        email_date = email_date.split(" (")[0]
        parsed_datetime = parse(email_date)
        parsed_datetime_est = parsed_datetime.astimezone(est_timezone)
        email_date = parsed_datetime_est.strftime("%Y-%m-%d %H:%M:%S")
    
    email_body = get_email_body(email_message)

    # Additional processing can be done here if required.

    # Print or save the extracted data to the SQL database.
    # Perform SQL database operations here.
    sql = "INSERT IGNORE INTO emails (emailuid, sendername, senderaddr, title, body, date) VALUES (%s, %s, %s, %s, %s, %s)"
    data = (email_id, sendername, senderaddr, email_subject, email_body, email_date)

    # Execute the INSERT query
    try:
        cursor.execute(sql, data)
        affected_rows = cursor.rowcount
        conn.commit()

        if affected_rows > 0:
            count += 1
            print("Saved (", count, ") - ", sendername, " | ", senderaddr, " | ", email_date, " | ", email_subject[:20], "... | ", email_body.replace("\n", " ")[:20], "...")
    except Exception as e:
        print(f"An error occured: {e}")
        conn.rollback()
    
    #if count >= 50: break      # Limit to this many emails to save

if count == 0: print ("No new emails found.")

save_current_timestamp()
cursor.close()
conn.close()
mail.logout()
