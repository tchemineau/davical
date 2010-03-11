<?php
/**
* CalDAV Server - handle BIND method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Morphoss Ltd - http://www.morphoss.com/
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
*/
dbg_error_log('BIND', 'method handler');
require_once('AwlQuery.php');

$request->NeedPrivilege('DAV::bind');

if ( ! $request->IsCollection() || ! $request->Exists() ) {
  $request->PreconditionFailed(403,'DAV::bind-into-collection',translate('The BIND Request-URI MUST identify a collection.'));
}

require_once('DAVResource.php');
$parent = new DAVResource( $parent_container );
if ( $parent->IsSchedulingCollection() ) {
  $request->PreconditionFailed(403, 'DAV::method-not-allowed',translate('The BIND method is not allowed at that location.') );
}

require_once('XMLDocument.php');
$reply = new XMLDocument(array( 'DAV:' => '' ));

$position = 0;
$xmltree = BuildXMLTree( $request->xml_tags, $position);

$segment = $xmltree->GetElements('DAV::segment');
$segment = $segment[0]->GetContent();

if ( preg_match( '{[/\\]}', $segment ) ) {
  $request->PreconditionFailed(403, 'DAV::name-allowed',translate('That destination name contains invalid characters.') );
}

$href    = $xmltree->GetElements('DAV::href')[0]->GetContent();
// $href    = $href[0]->GetContent();

$destination_path = $request->path;
if ( preg_match( '{[^/]$}', $destination_path ) ) $destination_path .= '/';
$destination_path .= $segment;
$destination = new DAVResource( $destination_path );
if ( $destination->Exists() ) {
  $request->PreconditionFailed(403,'DAV::can-overwrite',translate('A resource already exists at the destination.'));
}

$source = new DAVResource( $href );
if ( !$source->Exists() ) {
  $request->PreconditionFailed(403,'DAV::bind-source-exists',translate('The BIND Request MUST identify an existing resource.'));
}

if ( $source->IsPrincipal() || !$source->IsCollection() ) {
  $request->PreconditionFailed(403,'DAV::binding-allowed',translate('DAViCal only allows BIND requests for collections at present.'));
}

/*
  bind_id INT8 DEFAULT nextval('dav_id_seq') PRIMARY KEY,
  bound_source_id INT8 REFERENCES collection(collection_id) ON UPDATE CASCADE ON DELETE CASCADE,
  access_ticket_id TEXT REFERENCES access_ticket(ticket_id) ON UPDATE CASCADE ON DELETE SET NULL,
  parent_id INT8 REFERENCES collection(collection_id) ON UPDATE CASCADE ON DELETE CASCADE,
  dav_name TEXT UNIQUE NOT NULL,
  dav_displayname TEXT
*/

$sql = 'INSERT INTO dav_binding ( bound_source_id, access_ticket_id, parent_id, dav_name, dav_displayname )
VALUES( :target_id, :ticket_id, :container_id, :dav_name, :displayname )';
$params = array(
    ':target_id'    => $source->GetProperty('collection_id'),
    ':ticket_id'    => (isset($request->ticket) ? $request->ticket->id() : null),
    ':container_id' => $destination->GetProperty('collection_id'),
    ':dav_name'     => $destination_path,
    ':displayname'  => 'Bind to '.$source->GetProperty('DAV::displayname')
);
$qry = new AwlQuery( $sql, $params );

