{ //---------------------------------------------------------------------------
const scriptOptions = {
    // Set to an administrator email address.
    username: "",
    
    // Set to the password of that account.
    password: "",
    
    // The name of the brand that you want to copy everything from.
    copyFromBrand: "",
    
    // The name of the brand that you want to copy everything to.
    copyToBrand: "",
    
    // Set to true if you want to reorder the categories on the new brand
    //  to match the original brand. Not needed if the new brand is empty.
    //  (They will all be created in order.)
    reorder: true,
    
    // User will be prompted to fill out this form.
    prompt: true
};

function doPrompt( text, defaultText ) {
    let result = prompt( text, defaultText );
    if( result === null ) throw( "User cancelled." );
    return result;
}

if( scriptOptions.prompt ) {
    scriptOptions.username = doPrompt( "Enter your administrator username." );
    scriptOptions.password = doPrompt( "Enter your administrator password." );
    scriptOptions.copyFromBrand = doPrompt( "Brand name to copy FROM." );
    scriptOptions.copyToBrand = doPrompt( "Brand name to copy TO." );
}

//-----------------------------------------------------------------------------
let sessionId   = null;
let csrfToken   = null;

function log( text ) {
    // Log to the console in an obnoxious way.
    console.log( "%c" + text,
                           "color:white; font-size: 11px; background: #00f;" );
}

//-----------------------------------------------------------------------------
// Make a request to the Kayako API.
async function request( method, endpoint, body ) {
    method = method.toUpperCase();
    const url = endpoint;
    
    const options = {
        method,
        headers: {
            'Accept' : 'application/json'
        },
        cache: 'no-cache',
        credentials: 'omit'
    }
        
    if( sessionId ) {
        options.headers["X-Session-ID"] = sessionId;
        if( csrfToken ) {
            options.headers["X-CSRF-Token"] = csrfToken;
        }
    } else {
        options.headers["Authorization"] = "Basic "
          + btoa( scriptOptions.username
                    + ":"
                    + scriptOptions.password);
    }
    
    if( body ) {
        options.headers["Content-Type"] = "application/json";
        options.body = JSON.stringify(body);
    }
    
    log( `Making request to ${endpoint} (${method})` );
    
    try {
        // todo: exit on bad http status
        let response = await fetch( url, options );
        let ct = response.headers.get("X-CSRF-Token");
        response = await response.json();
        if( response.session_id ) {
            sessionId = response.session_id;
            if( ct ) {
                csrfToken = ct;
            }
        }
        return response;
    } catch( err ) {
        throw err;
    }
}

//-----------------------------------------------------------------------------
// Fetches a brand ID from a name given.
let brandData = null;
async function getBrandID( name ) {
    if( !brandData ) {
        brandData = (await request( "get", "/api/v1/brands.json?limit=1000" )).data;
    }
    
    for( brand of brandData ) {
        if( brand.name.toLowerCase() == name.toLowerCase() ) {
            return brand.id;
        }
    }
    return null;
}

//-----------------------------------------------------------------------------
// Reads results that may be paginated - fetches all of the pages and combines
//  them.
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

//-----------------------------------------------------------------------------
// Adds an article to be uploaded. They are queued in a buffer and then use a
//  bulk upload operation. Use the flushArticles function when you're done.
let articleBuffer = [];
function addArticle( titles, contents, sectionId, authorId, keywords, status, isFeatured, allowComments, tags ) {
    log( `Queueing article transfer: ${titles[0].translation}` );
    let body = {
        titles: titles,
        contents: contents,
        section_id: sectionId,
        status: status,
        is_featured: isFeatured,
        allow_comments: allowComments,
        tags: tags,
        // TODO: zero idea how these are handled across brands
        //files: files,
        //attachment_file_ids: attachmentFileIds 
    }
    if( authorId ) body.author_id = authorId;
    
    articleBuffer.push( body );
    // The limit is 200, but we want to be extra safe. I'm not sure what
    //  internal limits they have on jobs if they are doing a bunch of 
    //  200-width uploads at nearly the same time.
    if( articleBuffer.length >= 100 ) {
        flushArticles();
    }
}

async function flushArticles() {
    if( articleBuffer.length == 0 ) return;
    await request( "post", "/api/v1/bulk/articles.json", {articles:articleBuffer} );
    articleBuffer = [];
}

//-----------------------------------------------------------------------------
async function run() {
    // It's safe to say that a naive approach would be MUCH easier to implement,
    //  especially if you want it to be a robust operation.
    try {
        // Fetch the IDs of the brands we're copying from and to.
        let sourceBrand = await getBrandID( scriptOptions.copyFromBrand );
        let destBrand = await getBrandID( scriptOptions.copyToBrand );
        
        if( !sourceBrand || !destBrand ) {
            // Didn't find them. Typed incorrectly or don't exist.
            throw "Invalid brand(s) specified.";
        }
        
        // Fetch all of the categories, both on the source and dest side;
        //  they will be compared with each other to know which ones need
        //  to be created.
        let sourceCats = (await readPaginated( `/api/v1/categories.json?include=locale_field&limit=100&brand_id=${sourceBrand}` )).data;
        let destCats = (await readPaginated( `/api/v1/categories.json?include=locale_field&limit=100&brand_id=${destBrand}` )).data;
        
        // This is a name map between the two. If a mapping exists then we
        //  don't create that category on the destination.
        let destCatsNameMap = {};
        console.log( sourceCats, destCats );
        for( cat of destCats ) {
            // Use the first translation to detect for duplicates. Might
            //  cause problems with multilocale help desks, but that will be
            //  hard to account for.
            destCatsNameMap[cat.titles[0].translation] = cat.id;
        }

        // This is for the final reordering below. Categories will be added
        //  to the bottom, but they might exist somewhere else on the source
        //  side.
        let categoryIDOrder = [];
        
        // Work on 1 category at a time.
        for( const cat of sourceCats ) {
            log( "Processing " + cat.titles[0].translation );
            let destCatId = destCatsNameMap[cat.titles[0].translation];
            if( !destCatId ) {
                // Create it!
                let body = {
                    titles: cat.titles.map( x => ({locale: x.locale,
                                                   translation: x.translation}) ),
                    descriptions: cat.descriptions
                                         .map( x => ({locale: x.locale,
                                                      translation: x.translation}) )
                                         .filter( x => x.translation != null ),
                    brand_id: destBrand
                };
                
                // Kayako doesn't like an empty descriptions field.
                if( body.descriptions.length == 0 ) delete body.descriptions;
                
                let data = await request( "post", "/api/v1/categories.json", body );
                destCatId = data.data.id;
                log( "Created new ID");
            } else {
                // Same name already exists on the destination side.
                log( "Using existing ID");
            }
            // Log the creation order.
            categoryIDOrder.push( destCatId );
            
            // Read all of the sections in both source and dest categories.
            //  Sections will be empty in the destination if we just created
            //  it.
            const sourceSections = (await readPaginated( `/api/v1/sections.json?include=locale_field,tag&limit=100&category_ids=${cat.id}` )).data;
            const destSections = (await readPaginated( `/api/v1/sections.json?include=locale_field,tag&limit=100&category_ids=${destCatId}` )).data;
            
            // Copy sections for this category.
            for( const sourceSection of sourceSections ) {
                let sourceSectionId = sourceSection.id;
                let destSectionId = null;
                
                // Create a new section only if there is none that matches this
                //  source section.
                for( destSection of destSections ) {
                    if( destSection.titles[0].translation == sourceSection.titles[0].translation ) {
                        // Found one that matches, so we just map it.
                        destSectionId = destSection.id;
                        break;
                    }
                }
                
                log( `Processing section: ${sourceSection.titles[0].translation}` );
                if( !destSectionId ) {
                    log( `Creating it on destination.` );
                    // create it
                    console.log(sourceSection);
                    let body = {
                        titles           : sourceSection.titles.map( x => ({locale: x.locale,
                                                                            translation: x.translation}) ),
                        descriptions     : sourceSection.descriptions.map( x => ({locale: x.locale,
                                                                                  translation: x.translation}) )
                                                                     .filter( x => x.translation != null ),
                        visibility       : sourceSection.visibility,
                        tags             : sourceSection.tags.map( x => x.name ),
                        team_ids         : sourceSection.teams.map( x => x.id ),
                        article_order_by : sourceSection.article_order_by,
                        category_id      : destCatId
                    };
                    
                    // Kayako doesn't like empty lists.
                    if( body.descriptions.length == 0 ) delete body.descriptions;
                    
                    let data = await request( "POST", `/api/v1/sections.json`, body );
                    destSectionId = data.data.id;
                } else {
                    log( `Already exists on destination.` );
                }
                
                // And now... copy articles...
                const sourceArticles = (await readPaginated( `/api/v1/articles.json?include=locale_field&limit=100&section_id=${sourceSectionId}` )).data;
                const destArticles = (await readPaginated( `/api/v1/articles.json?include=locale_field&limit=100&section_id=${destSectionId}` )).data;
                
                for( const sourceArticle of sourceArticles ) {
                    let alreadyExists = false;
                    for( destArticle of destArticles ) {
                        if( destArticle.titles[0].translation == sourceArticle.titles[0].translation ) {
                            // Already exists. Skip this one.
                            log( `Skipping article that already exists: ${destArticle.titles[0].translation}` );
                            alreadyExists = true;
                            break;
                        }
                    }
                    if( alreadyExists ) continue;
                    
                    // Article will be queued for a bulk upload.
                    addArticle( sourceArticle.titles.map( x => ({locale: x.locale,
                                                                 translation: x.translation}) ),
                                sourceArticle.contents.map( x => ({locale: x.locale, // coalesque just in case--this is lazy.
                                                                   translation: x.translation ?? ""}) ),
                                destSectionId,
                                sourceArticle.author.id,
                                sourceArticle.keywords,
                                sourceArticle.status,
                                sourceArticle.is_featured,
                                sourceArticle.allow_comments,
                                sourceArticle.tags.map( x => x.id ),
                                sourceArticle.tags.map( x => x.id ) );
                    
                }
                
                // TODO: reorder sections :weary:
            }
        }

        flushArticles();
        
        // Reorder the categories to make them appear like the source list.
        // Kayako wants category_ids in csv format.
        // We remove duplicates - a rare scenario may have two categories named
        //  the same.
        if( scriptOptions.reorder ) {
            const exists = {};
            categoryIDOrder = categoryIDOrder.filter( a => {
                if( exists[a] ) return false;
                exists[a] = true;
                return true;
            });
            await request("put", "/api/v1/categories/reorder.json", {category_ids: categoryIDOrder.join(','), brand_id: destBrand})
        }
        
        log( `DONE.` );
    } catch ( error ) {
        log( `ERROR: ${error}` );
    }
}

log( "Starting operation." );
run();
}
