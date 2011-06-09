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
  <h3>Search for something you saw...</h3><form method="post" action="">
    
  <label class="" for="search_venue">What was the venue</label>
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
function SWYSGetEuropeanaSearchResults($search_terms,$search_place,$search_time,$mode,$search_sources) {
  
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
	  $url = 'http://api.europeana.eu/api/opensearch.rss' . '?searchTerms=';
	  // the search seems to deal with extra '&' so am doing that rather than conditional crap
	  if (!empty($search_terms)) {
	    $url .=  $search_terms . '&';
	  }
	  if (!empty($search_)) {
	    $url .= 'enrichment_period_term:' . $search_time .'&';
	  } 
  	  if (!empty($search_place)) {
	    $url .= 'enrichment_place_label:' . $search_place;
	  }
	  $url .= '&wskey=' . 'XADNJCFGME';
	  // $url = $mmg_import_Europeana_API_URL . '?searchTerms=' . $search_terms . '&enrichment_period_term=' . $search_time . '&enrichment_place_label=' . $search_place . '&wskey=' . $mmg_import_Europeana_API_key; // ### not getting the settings?
	  echo $url;
	  // could also look for completeness (<europeana:completeness>) values 1 - 10
          break;

  }

echo "<br />Loading list file for your search... <br />";

  //echo $url;

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
  
    if ($mode == 'display') {
    
      echo '<ul>';
      foreach($data->entry as $e){
        echo '<li><a href="' . $e->link[0]['href'] . 
             '">'.$e->title.'</a></li>';
      }
      echo '</ul>';
      
      //echo '<pre>';
      //print_r($data);
      //echo '</pre>';
    
    } else {
  
  // this will be custom field etc stuff in WordPress
  // process the file
  // ### this needs to be updated for SWYS as not importing but displaying back to screen
    
    if( sizeof($data) > 0 ){

      $i;
    
      echo 'Loading records into database...';    
      switch ($search_sources) {
	  case 'Europeana':
	    $terms = 'Keyword='.$search_terms.'&Place='.$search_place.'&Time='.$search_time; // for reference later
	    //echo '<pre>'.print_r($data).'</pre>';
            $i = mmgDoEuropeanaImportXML($data,$terms); // ### change this
            break;
      }
      //echo "Building SQL string... ";
  
      echo '<p>'.$i.' objects loaded.</p>';
        
    } else {
      echo 'Return size apparently < 0'; // April 2011 ###
    }
  }
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