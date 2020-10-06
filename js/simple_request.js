// Simple Kayako API request example.
// Paste into your brower console when on your Kayako page. You do not have to be logged
//  in and provide credentials directly.
//////////////////////////////////////////////////////////////////////////////////////////
{
const promptQuit = p => { let r = prompt(p); if(r === null) throw "Cancelled"; return r; }
let username = promptQuit("Enter your administrator email.");
let password = promptQuit("Enter your password.");
let spam_setting = promptQuit(
           "Spam aggressiveness to use. 1 = Very aggressive, 10 = effectively disabled.");

await (fetch( "/api/v1/settings", {
   method      : "PUT",
   credentials : 'omit',
   headers     : {
      "Content-type" : "application/json",
      "Authorization": `Basic ${btoa(username+":"+password)}`
   },
   body: JSON.stringify({values:{"email.spam_score":spam_setting}})
}).then(e => e.json()));
}