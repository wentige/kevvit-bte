import imaplib
import email
import chardet  # pip install chardet
import MySQLdb  # pip install mysqlclient
from dateutil.parser import parse # pip install python-dateutil
import configparser
import datetime
from email.header import decode_header

def get_last_run_timestamp():  # Get the last date and time that emailmonitor.py was executed
    try:
        with open("emailmonitorlog.txt", "r") as file:
            timestamp_str = file.read().strip()
            file.close()
            print("got:", timestamp_str)
            if timestamp_str == '': return None
            print("converting")
            parsed_datetime = parse(timestamp_str)
            formatted_date = parsed_datetime.strftime("%d-%b-%Y")
            print("converted:", formatted_date)
            return formatted_date
    except FileNotFoundError:
        return None
    
def save_current_timestamp():  # Save the current date and time to the emailmonitorlog.txt after exectuting
    current_timestamp = datetime.datetime.now()
    print(current_timestamp)
    timestamp_str = current_timestamp.strftime("%m-%d-%Y %H:%M:%S")
    print(timestamp_str)
    # Append the current timestamp to the log file
    try:
        with open("emailmonitorlog.txt", "a") as log_file:
            log_file.write(timestamp_str + "\n")
            log_file.close()
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

conn = MySQLdb.connect(**db_config)
cursor = conn.cursor()

mail = imaplib.IMAP4_SSL(imap_server)
mail.login(email_address, password)
mail.select(mailbox)

from_date = get_last_run_timestamp()
if from_date is None:
    from_date = "01-Jan-1970"


print("Saving emails sent after:", from_date)
status, uids = mail.uid("search", None, f'SINCE "{from_date}"')

count = 0
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


    # Parse date into SQL datetime format
    if email_date is not None:
        email_date = email_date.split(" (")[0]
        parsed_datetime = parse(email_date)
        email_date = parsed_datetime.strftime("%Y-%m-%d %H:%M:%S")
    
    email_body = get_email_body(email_message)

    # Additional processing can be done here if required.

    # Print or save the extracted data to the SQL database.
    # Perform SQL database operations here.
    sql = "INSERT IGNORE INTO emails (emailuid, sendername, senderaddr, title, body, date) VALUES (%s, %s, %s, %s, %s, %s)"
    data = (email_id, sendername, senderaddr, email_subject, email_body, email_date)

    # Execute the INSERT query
    cursor.execute(sql, data)
    conn.commit()
    print("Saved (", email_id, ")", count, " - ", sendername, " | ", senderaddr, " | ", email_date, " | ", email_subject[:20], "... | ", email_body.replace("\n", " ")[:20], "...")
    count += 1
    if count >= 5: break # Limit to this many emails to save

if count == 0: print ("No new emails found.")

save_current_timestamp()
cursor.close()
conn.close()
mail.logout()
