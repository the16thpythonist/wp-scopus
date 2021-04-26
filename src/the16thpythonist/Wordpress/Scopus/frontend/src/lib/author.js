/* eslint-disable */
/*
    So here are some thoughts about how this thing is supposed to work:
    We have a ScopusAuthor object and then in theory we should be able to make this a data property of a
    scopus component and then directly v-model the input fields to the properties of this object directly.
    This would no doubt work with the string name and the array properties, but it might be a different thing
    with the affiliations, because what I would need for the input component there would be an object which is
    a direct associative array from the names of the affiliations to the blacklist/whitelist choice. But the
    more concise way to represent them would be as objects of their own having the choice, the name and the ID
    as separate properties.
    -> Maybe I just have to extend the input component to be able to deal with that sort of thing.
 */

// FUNCTIONS

function emptyScopusAuthor() {
    return new ScopusAuthor(
        '',
        '',
        '',
        [],
        [],
        {}
    )
}

// CLASSES


function ScopusAuthor(
    postId,                   // string
    firstName,                  // string
    lastName,                   // string
    scopusIds,                  // array of strings
    categories,                 // array of strings
    affiliations,                // assoc array string -> ScopusAuthorAffiliation
    whitelist,
    blacklist,
) {
    this.id = postId;
    this.firstName = firstName;
    this.lastName = lastName;
    this.scopusIds = scopusIds;
    this.categories = categories;
    this.affiliations = affiliations;
    this.whitelist = whitelist;
    this.blacklist = blacklist;

    this.isAffiliationWhitelisted = function(affiliationId) {
        return this.whitelist.includes(affiliationId);
    }
}

function ScopusAuthorAffiliation(
    id,                         // string
    name,                       // string
    whitelist                   // boolean
) {
    this.name = name;
    this.id = id;
    this.whitelist = whitelist;
}

export default {
    emptyScopusAuthor: emptyScopusAuthor,
    ScopusAuthor: ScopusAuthor,
    ScopusAuthorAffiliation: ScopusAuthorAffiliation
}