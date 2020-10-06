// Paginated Results
// --
// Most of the API functions that return collections or arrays will want to split things
// up into pages. You can potentially set the limit to be very high to avoid handling
// paged results when you want to work on a complete set, but I’m not sure what the actual
// limit is. Keep in mind that this is bad practice when working with large data sets. If
// you want to process everything, you should work on it like a stream. Here are functions
// to operate on the complete data set.
//
// I recommend setting a higher limit in the query to avoid too many chained API calls.
// Limit can be set with the query argument `limit`. Default limit is 10. (see
// complex_request.js for `request` function)
//////////////////////////////////////////////////////////////////////////////////////////

//----------------------------------------------------------------------------------------
// Reads results that may be paginated - fetches all of the pages and combines them.
async function readPaginated( endpoint, body ) {
    let result = await request( "GET", endpoint, body );
    let readNext = result.next_url;

    while( readNext ) {
        let result2 = await request( "GET", readNext, body );
        for( entry of result2.data ) {
            result.data.push( entry );
        }
        readNext = result2.next_url;
    }

    return result;
}

//----------------------------------------------------------------------------------------
// Reads results that may be paginated and runs the callback function on each returned
// dataset.
async function processPaginated( endpoint, body, callback ) {
    let result = await request( "GET", endpoint, body );
    let readNext = result.next_url;
    callback( result );
    while( readNext ) {
        let result2 = await request( "GET", readNext, body );
        callback( result2 );
        readNext = result2.next_url;
    }
}
