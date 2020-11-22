<?php
// This is based off of the source for the metadata converter that can be accessed
//  through the admin UI. This can be run from the command line for automation
//  e.g. cat /metadata.xml | php /simplesamlphp/www/parse_metadata.php >> /simplesamlphp/metadata/saml20-idp-remote.php
//
// Note that this doesn't check for any errors, so it should be considered unstable.
// You will get undefined behavior if any error occurs if you write directly to your
//  configuration file like that. I suggest doing it manually. You only need to update
//  the metadata every 3 years I believe.
require_once('_include.php');

$xmldata = stream_get_contents(STDIN);

if (!empty($xmldata)) {
    \SimpleSAML\Utils\XML::checkSAMLMessage($xmldata, 'saml-meta');
    $entities = \SimpleSAML\Metadata\SAMLParser::parseDescriptorsString($xmldata);

    // get all metadata for the entities
    foreach ($entities as &$entity) {
        $entity = [
            'shib13-sp-remote'  => $entity->getMetadata1xSP(),
            'shib13-idp-remote' => $entity->getMetadata1xIdP(),
            'saml20-sp-remote'  => $entity->getMetadata20SP(),
            'saml20-idp-remote' => $entity->getMetadata20IdP(),
        ];
    }

    // transpose from $entities[entityid][type] to $output[type][entityid]
    $output = \SimpleSAML\Utils\Arrays::transpose($entities);

    // merge all metadata of each type to a single string which should be added to the corresponding file
    foreach ($output as $type => &$entities) {
        $text = '';
        foreach ($entities as $entityId => $entityMetadata) {
            if ($entityMetadata === null) {
                continue;
            }

            // remove the entityDescriptor element because it is unused, and only makes the output harder to read
            unset($entityMetadata['entityDescriptor']);

            $text .= '$metadata[' . var_export($entityId, true) . '] = ' .
                var_export($entityMetadata, true) . ";\n";
        }
        $entities = $text;
    }
    
    echo implode( "", $output );
}

