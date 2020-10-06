// Complex Kayako API request example.
// Paste into your brower console when on your Kayako page. You do not have to be logged
//  in and provide credentials directly.
//////////////////////////////////////////////////////////////////////////////////////////
{ // Wrap everything in a block so we clean up everything when done.
//----------------------------------------------------------------------------------------
const scriptOpts = {
   username: "",
   password: ""
};

const reqPrompt = p => { let r = prompt(p); if(r === null) throw "Cancelled"; return r; }
scriptOpts.username = reqPrompt( "Enter your administrator email." );
scriptOpts.password = reqPrompt( "Enter your administrator password." );

//----------------------------------------------------------------------------------------
// Make a request to the Kayako API.
// method   : "GET/POST/PUT/DELETE"
// endpoint : "/api/v1/etc" or such.
// body     : Object to serialize to json (optional param)
let mySessionId = null;
async function request( method, endpoint, body ) {
   const options = {
      method  : method.toUpperCase(),
      headers : { 'Accept' : 'application/json' }, // Return JSON.
      cache   : 'no-cache', // Disable cache just in case.
      credentials: 'omit'  // Can't use the site's cookies and such because we
   }                       //  don't have the CSRF token.
      
   // When making multiple calls, we will have a session ID to skip authentication after
   //  the first time. It also requires a CSRF token, which is in the header of our first
   //  request with Basic Auth.
   if( mySessionId ) { 
      options.headers["X-Session-ID"] = mySessionId;
      options.headers["X-CSRF-Token"] = csrfToken;
   } else {
      // First request: we need to give our credentials through Basic Auth.
      const code = btoa(`${scriptOpts.username}:${scriptOpts.password}`);
      options.headers["Authorization"] = `Basic ${code}`;
   }
   
   if( body ) {
      // Add the body as serialized JSON. Set the content type too.
      options.headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(body);
   }
   
   let response = await fetch( endpoint, options );
   json = await response.json();
   if( response.session_id ) {
      // session_id will be found in the first request.
      mySessionId = response.session_id;
      csrfToken   = response.headers.get( "X-CSRF-Token" );
   }
   return json;
}

//-----------------------------------------------------------------------------
// Sample code: reading and setting the spam filter aggressiveness.
{
   const settings = (await request( "get", "/api/v1/settings" )).data;
   let current_spam_score;
   for( const a of settings ) {
      if( a.category == "email" && a.name == "spam_score" ) {
            current_spam_score = a.value;
            break;
      }
   }
   let new_score = prompt(
      `Current spam setting is ${current_spam_score}.\n\n`
      +`What do you want to set it to?\n`
      +`1 = Most aggressive, 10 = Effectively off.` );
   if( !new_score || new_score < 1 || new_score > 10 ) throw "Cancelled/Invalid input";
   await request( "put", "/api/v1/settings", {
                     values: {
                        "email.spam_score": new_score
                     }
                  });
   console.log( "Done." );
}
}