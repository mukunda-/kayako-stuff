## Making Easy Kayako API Requests

Navigate to the Kayako instance of choice. You don’t need to log in, but maybe do that anyway.

Open the browser Inspector (F12 usually) and go to the console. Since you are on the Kayako instance page, you can use JavaScript to easily send requests to the API. Normally this isn’t possible due to CORS restrictions, but since you are on the Kayako page (same-origin), you can make requests to it from the browser console.

*simple_request.js* is an example of a simple one-off request.

*complex_request.js* provides a more comprehensive request function for chained API calls using a single session.

*paginated_results.js* is an example of reading datasets from the API that are paged across multiple calls.
