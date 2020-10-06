# A template for calling the Kayako API with user credentials.
# (C) 2020 Mukunda Johnson
#-----------------------------------------------------------------------------------------
import json, re, urllib, datetime, getpass
from http.client import HTTPSConnection
from base64 import b64encode

#/////////////////////////////////////////////////////////////////////////////////////////
domain = input( "Enter your Kayako instance name (e.g., yourcompany.kayako.com): " )
# Let the kayako.com be optional.
domain   = domain.strip().replace(".kayako.com", "")
username = input( "Enter your Kayako email/username: " ).strip()
password = getpass.getpass( "Enter your Kayako password: " )

# These are used after the first API call.
session_id = None
csrf_token = None

#-----------------------------------------------------------------------------------------
# Makes a request to the Kayako API.
# method = "GET"/"POST"/"PUT"/"DELETE"
# endpoint = "/api/v1/..."
def request( method, endpoint, query = None, body = None ):
   global session_id, csrf_token
   
   if query:
      # Append query to endpoint.
      endpoint += "?" + urllib.parse.urlencode(query)
   
   c = HTTPSConnection( f"{domain}.kayako.com" )
   headers = { "Accept" : "application/json" }
   
   if not session_id:
      auth = b64encode( bytes(f"{username}:{password}", "utf-8") ).decode( "ascii" )
      headers["Authorization"] = f"Basic {auth}"
   else:
      headers["X-Session-ID"] = session_id
      headers["X-CSRF-Token"] = csrf_token
      
   if body:
      headers["Content-Type"] = "application/json"
      body = json.dumps(body);
      
   c.request( method.upper(), endpoint, headers = headers, body = body );
   response = c.getresponse();
   data = json.loads(response.read());
   
   if "session_id" in data:
      session_id = data['session_id']
      csrf_token = response.getheader( "X-CSRF-Token" )
   
   return data

#-----------------------------------------------------------------------------------------
# Sample code to read spam aggressiveness setting from /api/v1/settings
# https://help.kayako.com/hc/en-us/articles/360006456439

settings = request( "get", "/api/v1/settings" )

for setting in settings["data"]:
    if setting["category"] == "email" and setting["name"] == "spam_score":
        print( f"Spam setting is {setting['value']}." )
        break
