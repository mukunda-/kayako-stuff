Example Azure AD SAML Login Service for Kayako
==============================================

What this is
------------

Using SimpleSAMLphp, kayako.php implements a simple service provider that forwards login
requests to Azure AD, and then transforms responses for Kayako's SSO API.

Setup required to test
----------------------

(1) Setup Azure AD SSO.

    1. Create a new app and configure SAML SSO for it.
    2. Set Reply URL to https://localhost/module.php/saml/sp/saml2-acs.php/default-sp
       (replacing localhost if you are using a public host).
    3. Set claim "KayakoSecret" to what you configure in Kayako.
    4. Set claim "KayakoURL" to the URL for your Kayako domain,
       e.g. "https://company.kayako.com" with no trailing slash.

(2) Copy details from Azure AD settings and configure this service.

    1. Copy Federation Metadata XML from Azure into this folder as "metadata.xml"
    2. Rename config.sh.example to config.sh.
    3. Copy Identifier (Entity ID) from Basic SAML Configuration into config.sh.
    4. Set the administrator password. This is used to log into SimpleSAMLphp's admin panel.

(3) Build and run Docker image. It will capture the current folder and configuration files.

    1. docker build --tag kayako_saml_test .
    2. docker run -it -p 443:443 kayako_saml_test

(4) Kayako SSO setup.

    1. Point Kayako login to https://localhost/kayako.php
    2. Point logout to anywhere (perhaps Azure AD Apps page)
    3. Set secret to match KayakoSecret claim.

(5) Test.

    1. Visit https://localhost to test the SimpleSAML admin site. The admin password is
       "securepassword123" by default.
    2. Ignore any SSL certificate errors. A self-signed certificate is generated for an
       https connection (and only https is supported by the nginx config provided).
