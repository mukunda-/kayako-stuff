Example to log into Kayako from another site using Kayako credentials.

How it works:

(1) User inputs credentials into form.
(2) Backend verifies the credentials with Kayako using the /api/v1/me endpoint.
    - This endpoint will also handle blocking excessive login attempts and give a warning.
(3) On success, a JWT token is generated using the Kayako key and the user is directed to SSO login.

Note that there is no logout endpoint. That can be specified on Kayako's end, but it isn't entirely necessary for many configurations.
