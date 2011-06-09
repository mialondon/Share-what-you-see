<?php
/*
 * Functions for the Share What You See plugin
 * 
 * File information:
 * Contains functions to query the APIs for the terms provided
 * 
 */

/*
 * Notes on import adaptations:
 * 
 * Notes for Europeana:
 * Some of the fields are less clean than one would like, but apparently that's
 * down to the data providers
 * 
 * Import written for: WordPress 3 for generic WordPress installs
 *  
 */

/*
 * Needs adapting for SWYS ###
 * ### also not to use the Europeana enrichment fields cos they weren't reliable
 * Was: Specialised search box for Tag Duel - asks for period (time) and place
 * Values may be free-text or come from the OpenSearch term list [Ian]
 * Might be nice to add optional free-text search if it's likely to return lots of results
 * TO DO: should give a venue list as typing one in would give mismatches
 * TO DO: should have the ability to search by city (which means knowing which venues are in which city)
 */
function SWYSPrintSearchForm() {
  ?>
  <h3>Search for something you saw so you can share it in a blog post...</h3><form method="post" action="">
    
  <label class="" for="search_venue">What was the venue?</label>
	<input name="search_venue" size="30" tabindex="1" value="" id="search_venue" autocomplete="on" type="text"><br />
<!--  <label class="" for="search_accession">Do you know the accession number?</label>
	<input name="search_accession" size="30" tabindex="2" value="" id="search_accession" autocomplete="on" type="text"><br />-->
  <label class="" for="search_title">Or maybe the object/artwork title?</label>
	<input name="search_title" size="30" tabindex="2" value="" id="search_title" autocomplete="on" type="text"><br />
  <label class="" for="search_term">Or just try a general search</label>
	<input name="search_term" size="30" tabindex="3" value="" id="search_term" autocomplete="on" type="text"><br />
	  
          <input type="submit" name="search" value="Go!" />

          </form>

  <?php
}


/*
 * Inputs: $terms is the search terms given in the box, $mode is display or import
 */
function SWYSGetEuropeanaSearchResults($search_terms,$search_title,$search_venue,$mode,$search_sources) {
  
  $api_provider;
  $type;
  // get site options ## but IIRC this don't work right now
  $mmg_import_Europeana_API_key = $mmg_import_options_arr["mmg_import_Europeana_API_key"];
  $mmg_import_Europeana_API_URL = $mmg_import_options_arr["mmg_import_Europeana_API_URL"];

  // ### to do re-write later to get rid of the case statements

  switch ($search_sources) {
      case 'Europeana':
          $type = 'xml';
          $api_provider = 'Europeana'; // ### will presumably need to be the source provider? Check the field
// top tip - for more detailed result listings use .json
	  $url = 'http://api.europeana.eu/api/opensearch.rss' . '?searchTerms=';
	  // the search seems to deal with extra '&' so am doing that rather than conditional crap
	  if (!empty($search_terms)) {
	    $url .=  $search_terms . '&';
	  }
	  if (!empty($search_title)) {
	    $url .= 'title:' . $search_title .'&';
	  } 
  	  if (!empty($search_venue)) {
	    $url .= 'dataProvider:' . $search_venue;
	  }
	  $url .= '&wskey=' . 'XADNJCFGME';
	  echo $url;
	  // could also look for completeness (<europeana:completeness>) values 1 - 10
          break;

  }

  echo "<br />Loading the results for your search... <br />";

  $ch = curl_init(); // relies on curl
  curl_setopt($ch, CURLOPT_USERAGENT, 'API client');      
  curl_setopt($ch, CURLOPT_URL, $url); 
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $output = curl_exec($ch); 
  curl_close($ch);

  if (!empty($output)) { 
    switch ($type) {
      case 'xml':
	if ($search_sources == 'Europeana') {
          $rss = simplexml_load_string($output);
	  $data = $rss->channel;
	  //$atom = $rss->channel;
	  //$data = $atom->children('atom');
	}
        break;
    }
  }

  if (!empty($data)) {

  // the Europeana Opensearch RSS result set only provides access to title, URI and thumbnail in list view
  // but all we need is thumbnail so the user can identify the thing they saw
  // (can always update to use the json version)
  // so show it and pass the URI to Owen's createSWYSPost function
      
     // testing bit
    echo '<ul>';
    foreach($data->item as $e){
      // get image URL, test to make sure it's an image
      $str = $e->enclosure['url'];
      $pos = strrpos($str, "jpeg");
      if ($pos != false) { // note: three equal signs
        echo "<img src=".$e->enclosure['url']." alt='thumbnail of ".$e->title."'>";
      }
      // print the object title
      echo '<form method="post" action=""><h3><a href="' . $e->link. 
	   '">'.$e->title.'</a></h3>';
      echo '<input name="object_url" value="test" id="object_url" type="hidden"><input type="submit" name="objecttoblog" value="Use this one" /></form>';

    }
    echo '</ul>';
    
  
/*     // testing bit      
    echo '<pre>';
    print_r($data);
    echo '</pre>';
*/   
    
  } else {
    echo '<br />No results found for that search term. Try again!';
  }
}

function mmgDoEuropeanaImportXML($data,$terms) {
  $i;
  //echo 'hello';
  // Iterate over each <item> in the channel element.
  echo '<pre>'.print_r($data).'</pre>';
  echo 'size: '.sizeof($data->item) ;
    foreach($data->item as $item) {
      echo 'in loop<br />';
//  foreach($data->channel->item as $doc) {
    // Now we need to fetch the URL identified in the <link> element
    $ch = curl_init(); // relies on curl
    curl_setopt($ch, CURLOPT_USERAGENT, 'API client');    
//    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
    curl_setopt($ch, CURLOPT_URL, $doc->link);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $resultdoc = curl_exec($ch);
    curl_close($ch);

    // Check that the link element really returned us something
    if ( !empty($resultdoc) ) {
      // Parse what was returned
      $xmldoc = simplexml_load_string($resultdoc);

// process rss for title, maker/artist (if we can get it), date, description, image/thumbnail, provider

    }
  }
}

function mmgInsertObject($object_name,$accession_number,$api_provider, $data_source_url, $source_display_url, $description, $date_earliest, $date_latest, $interpretative_date, $interpretative_place, $image_url, $terms) {
  global $wpdb;
  if (!empty($image_url)) {
    // debug echo '<h2>in insert statement</h2>';
    $sqlresult = $wpdb->query( $wpdb->prepare( "
      INSERT IGNORE INTO ". db_objecttable . " 
      (".db_objecttablefields.")
      VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s )" ,
      array( $object_name,$accession_number,$api_provider, $data_source_url, $source_display_url, $description, $date_earliest, $date_latest, $interpretative_place, $interpretative_place, $image_url, $terms ) ) ); 

      if ( $sqlresult == 0 ) {
        echo 'OK <br/>';
      }
      else { 
        // echo 'Error : ' . mysql_errno() . ' ' . mysql_error() . $object_name . '<br/>';        
        // echo $object_name . '-' . $accession_number . '-' . $api_provider . '-' .  $data_source_url. '-' .  $source_display_url . '-' .  $description . '-de:' .  $date_earliest . '-dl:' .  $date_latest. '-id:' .  $interpretative_date. '-ip:' .  $interpretative_place . '-' .  $image_url . '-' . $terms . '.<br/>';
      }

  }
  else {
    echo 'Item has no image....';
  }
}

function createSWYSPost($europeana_url) {

$xml = file_get_contents($europeana_url);
// Create DOMDocument and load the xml to parse
$doc = new DOMDocument();
$doc->loadXML($xml);

// Create DOMXPath object so we can use XPath queries
$xpath = new DOMXPath($doc);
$xpath->registerNameSpace('srw', 'http://www.loc.gov/zing/srw/');
$xpath->registerNameSpace('europeana', 'http://www.europeana.eu');
$xpath->registerNameSpace('dc', 'http://purl.org/dc/elements/1.1/');
$xpath->registerNameSpace('dcterms', 'http://purl.org/dc/terms/');
$records = $doc->getElementsByTagName("dc");

$xpath_description = "./dc:description/text()";
$xpath_title = "./dc:title/text()";
$xpath_provider = "./dcterms:isPartOf/text()";
$xpath_image = "./europeana:object/text()";

foreach($records as $record) {

$nodeList_title = $xpath->evaluate($xpath_title,$record);
if ($nodeList_title->length > 0) {
$content_title = $nodeList_title->item(0)->nodeValue;
$title = $content_title;
} else {
$title = "UNKNOWN TITLE";
}

$nodeList_description = $xpath->evaluate($xpath_description,$record);
if ($nodeList_description->length > 0) {
$content_description = $nodeList_description->item(0)->nodeValue;
$description = $content_description;
} else {
$description = "NO DESCRIPTION";
}

$nodeList_provider = $xpath->evaluate($xpath_provider,$record);
if ($nodeList_provider->length > 0) {
$content_provider = $nodeList_provider->item(0)->nodeValue;
$provider = $content_provider;
} else {
$provider = "UNKNOWN PROVIDER";
}

$nodeList_image = $xpath->evaluate($xpath_image,$record);
if ($nodeList_image->length > 0) {
$content_image = $nodeList_image->item(0)->nodeValue;
$image = $content_image;
} else {
$image = "";
}

}

$content = "";
if (!empty($image)) {
$content .= "<a href=\"".$image."\" alt=\"".$title."\" />";
}
if (!empty($title)) {
$content .= "<strong>Title:</strong> ".$title."<br />";
}
if (!empty($description)) {
$content .= "<strong>Description:</strong> ".$description."<br />";
}
$new_post = array(
'post_title' => $title,
        'post_content' => convert_chars($content)
        //Default field values will do for the rest - so we don't need to worry about these - see http://codex.wordpress.org/Function_Reference/wp_insert_post
);

$post_id = wp_insert_post($new_post);

if (is_object($post_id)) {
//error - what to do?
return false;
}
elseif ($post_id == 0) {
//error - what to do?
return false;
}
else {
add_post_meta($post_id, 'object_title', $title);
// add_post_meta($post_id, 'object_maker', $maker);
// add_post_meta($post_id, 'object_date', $date);
add_post_meta($post_id, 'object_provider', $provider);
//other custom fields here if required
}
return $post_id;
}

function getSingleValue($document,$xpath) {
  $result;
  $nodelist = $document->xpath($xpath);
  echo "nl: $nodelist <br/>";
  if ( count($nodelist) > 0 ) {
    $result = $nodelist[0];
  }
  else {
    $result = "";
  }
  return $result;
}

?>