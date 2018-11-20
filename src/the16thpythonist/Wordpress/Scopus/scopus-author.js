
var affiliations_already = [];
var id_input = jQuery('#scopus_author_id');

// This is the container element, which will contain all the actual entries for affiliation displays
var affiliation_wrapper = jQuery('div#affiliation-wrapper');

// This is the container element, which will contain the log messages about the fetch process
var affiliation_status_log = jQuery('#affiliation-fetch-log');


/**
 * Writes a log message into the log container right on top of the affiliation display
 *
 * CHANGELOG
 *
 * Added 03.11.2018
 *
 * @param message
 * @param color
 */
function logStatus(message, color = 'grey') {

    // Creating a new element, which will contain the log message
    let log = jQuery(`<p style='color: ${color}; margin: 0px;'>${message}</p>`);

    // Adding the element to the log container
    log.appendTo(affiliation_status_log);

    // Logging the message into the console as well
    console.log(message);
}


/**
 * This function will return an array with all the scopus IDS of the author, whose page is currently displayed.
 *
 * The scopus author ID's of an author are being displayed/need to be written into a text area input widget. Each
 * row of the input widget contains one alternative scopus ID of the author (due to management issues within scopus,
 * it is possible that one author has multiple profiles and thus ID's)
 *
 * CHANGELOG
 *
 * Added 03.11.2018
 *
 * @return {Array}
 */
function getIDs() {
    var value, ids;
    ids = [];
    value = id_input.attr('value');
    if (value.includes("\n")) {
        ids = value.split("\n");
    } else {
        ids.push(value);
    }
    return ids;
}

/**
 * Will trigger an AJAX request, which will cause the server to fetch the affiliation IDs for the author
 *
 * CHANGELOG
 *
 * Added 03.11.2018
 *
 * @param author_id The scopus author ID, for which to fetch the affiliations
 */
function fetchAffiliations(author_id) {
    // Displaying to the user, that the fetch process is starting
    logStatus(`Starting to fetch the affiliations for ID "${author_id}"`);

    // This AJAX request will trigger a background process in the server, which will start to fetch the affiliation
    // ID's for the given author id and then write them into a temporary data post. Later on we will request the data
    // from this data post and update the local list with it.
    // We are doing the fetching on the server side here, because we already have a fully working scopus API in PHP,
    // but nothing at all in JS.
    jQuery.ajax({
        url:        ajaxurl,
        type:       'Get',
        timeout:    60000,
        dataType:   'html',
        async:      true,
        data:       {
            action:     'scopus_author_fetch_affiliations',
            author_id:  author_id
        },
        success: function(response) {
            console.log(response);
            logStatus(`...All affiliations for "${author_id}" fetched!`, 'green');
        },
        error: function(response) {
            logStatus(`...There was an error with FETCHING for author "${author_id}"!`, 'red');
        }
    })
}

/**
 * Updates the affiliation list display by requesting the temporary list of fetched affiliation ids from the server
 * and in case there is a new ID, that is not already being displayed, assembles a new HTML element and adds it.
 * This function executes with a frequency of 1Hz after being called once.
 *
 * CHANGELOG
 *
 * Added 03.11.2018
 */
function updateAffiliations(once=false) {

    // Getting all the author ids for the author from the input field, that displays them and then using them to
    // create the names of the temporary data posts, that actually hold the affiliation data
    let ids = getIDs();
    let names = getFilenames(ids);

    // Executing the whole update process for each author ID separately
    names.forEach(function (name) {
        try {
            // Reading the raw data string from the temporary file, that contains the affiliation ids.
            // The affiliation ids were saved as an array in JSON format by the server. The keys of that array are the
            // actual affiliation IDs and the values are the string names of these affiliations.
            let data = readDataPost(name);
            let affiliations = JSON.parse(data);

            // Here we calculate an array 'difference', which only contains those affiliation IDs that are not already
            // displayed, by comparing with the global list, that keeps track of the already displayed affiliations
            let keys = Object.keys(affiliations);
            let difference = keys.filter(x => !affiliations_already.includes(x));

            let value, whitelist_checked, blacklist_checked;
            difference.forEach(function (key) {
                // This is really complicated stuff, because the way the affiliation display has to be formatted for
                // saving later.
                // console.log(key);
                value = affiliations[key];

                // Here the new HTML element to be added to the affiliation list gets "assembled"
                if (value['whitelist'] === true) { whitelist_checked = ' checked'; } else { whitelist_checked = ''; }
                if (value['blacklist'] === true) { blacklist_checked = ' checked'; } else { blacklist_checked = ''; }
                let checkbox_whitelist_string = '<input type="checkbox" name="whitelist-' + key + '" value="1"' + whitelist_checked +'>';
                let checkbox_blacklist_string = '<input type="checkbox" name="blacklist-' + key + '" value="1"' + blacklist_checked +'>';
                let description_string = '<p class="first">' + key + ': ' + value['name'] + '</p>';
                let html_string = '<div class="affiliation-row">' + description_string + checkbox_whitelist_string + checkbox_blacklist_string + '</div>';
                let row_element = jQuery(jQuery.parseHTML(html_string));
                row_element.appendTo(affiliation_wrapper);
                affiliations_already.push(key);
            });
        } catch (err) {
            console.log("There was an error with UPDATING publications")
        }
    });

    if (!once) {
        // At the end of the function we set a timer to execute this very function 1 second later.
        // This is actually the preferred method of implementing a repeating function, because the new function only ever
        // gets called when the function succesfully finishes fist.
        // If it was just set to call the function in set Intervals, the function could hang up and then after some time
        // some thousand hung up functions could freeze the browser.
        setTimeout(updateAffiliations, 2000);
    }
}

/**
 * Deletes all the temporary files containing the affiliation ids on the server for the author of the current page
 *
 * CHANGELOG
 *
 * Added 04.11.2018
 */
function deleteData() {
    // We delete all the temporary files for each author id. This is necessary to be done if the affiliation ids have
    // to be fetched anew
    let names = getFilenames(getIDs());
    names.forEach(function (name) {
        deleteDataPost(name);
    });

    logStatus('All old files deleted.')
}

/**
 * Removes all the lines with the affiliation names form the displayed table. Also clears the array containing the
 * ids of the already displayed affiliation ids
 *
 * CHANGELOG
 *
 * Added 04.11.2018
 */
function clearDisplay() {
    // Removing all the actual entries from the affiliation display, but NOT the first entry, which is the captions
    // of the columns
    jQuery('div.affiliation-row').remove();
    // We also need to clear the array of affiliation ids already displayed, since there are none displayed anymore!
    affiliations_already = [];

    logStatus('All affiliations from the last update cleared.')
}

/**
 * Returns an array, containing the string names of the temporary files with the affiliation ids on the server
 *
 * CHANGELOG
 *
 * Added 03.11.2018
 *
 * @return {Array}
 */
function getFilenames(ids) {
    let names = [];
    ids.forEach(function (id) {
        let name = 'affiliations_author_' + id + '.json';
        names.push(name);
    });
    return names;
}



// Here we register the callback function for the button to update the affiliations
let update_button = jQuery('button#update-affiliations');
update_button.on('click', function () {

    // First we delete the old temporary files on the server, that contain the affiliations from the previous update
    deleteData();
    clearDisplay();


    // We send the AJAX request to the server and tell him to start fetching the affiliations through the API
    let ids = getIDs();
    ids.forEach(function (id) {
        fetchAffiliations(id);
    });

    // We start a permanent update process, which will display the new affiliations as they are saved by the server
    setTimeout(updateAffiliations, 3000);

    // This is important. Only when false is returned it will prevent the page from reloading after the button
    // has been pressed
    return false;
});