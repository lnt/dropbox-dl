<?php

function get_dropbox_files($url, &$files, $recursive = true, $validExtensions = array(), $folder = '/')
{
    println('Checking %s', $url);
    $cookieJar = "cookie.txt";
    $cookieFile = "cookie.txt";
    $options = array('cookieJar' => $cookieJar, 'cookieFile' => $cookieFile);

    $ch = curl_init();
    $contents = get_html($ch,$url,$options);
    //$contents  = file_get_contents($url);
    $cookies = extractCookies($options['cookieFile']);

    $search = '.responseReceived("';
    $start = strstr($contents, $search);
    list($line) = explode(PHP_EOL, $start);
    $jsonStart = strpos($line, $search) + strlen($search);
    $jsonEnd = strrpos($line, '")');
    $json = substr($line, $jsonStart, $jsonEnd - $jsonStart);
    $jsonStr = json_decode('"' . $json . '"', true);
    $result = json_decode($jsonStr, true);


    //print_r( $result);
    $folder_share_token = $result['folder_share_token'];
    //print_r( $folder_share_token);
    //println("=======SAVED COOKIES START========");
    //print_r($cookies);
    //println("=======SAVED COOKIES END========");
    $next_request_voucher = $result["next_request_voucher"];

    while (true) {
        //println("Inside loop");
        if (array_key_exists('entries', $result)) {
            //print("size:".count($result['entries']));
            foreach ($result['entries'] as $entry) {
                //println("<====entry====>");
                //print_r($entry);
                //println("</===entry====>");
                $isDir = filter_var($entry['is_dir'], FILTER_VALIDATE_BOOLEAN);
                if ($recursive && $isDir) {
                    $next_folder = str_replace("//", "/", $folder."/".$entry['filename']."/");
                    println("===> ".$next_folder);
                    get_dropbox_files($entry['href'], $files, $recursive, $validExtensions,
                        $next_folder);              
                    // get_dropbox_files($entry['href'], $files, $recursive, $validExtensions,
                    //     sprintf('/%s/', basename(strtok($entry['href'], '?'))));
                } elseif (!$isDir) {
                    $add = replace_query_params($entry['href'], ['dl' => 1]);
                    if (!array_key_exists($folder, $files)) {
                        $files[$folder] = array();
                    }
                    if (count($validExtensions) > 0) {
                        $ext = pathinfo(strtok($add, '?'), PATHINFO_EXTENSION);
                        if (in_array(strtolower($ext), $validExtensions) && !in_array($add, $files[$folder])) {
                            $files[$folder][] =  array($add,$entry['bytes']);;
                        }
                    } elseif (!in_array($add, $files[$folder])) {
                        $files[$folder][] = array($add,$entry['bytes']);
                    }
                }
            }
        }

        if(empty($result["next_request_voucher"])){
            break;
        } else {
            $new_rsp = post_form($ch,'https://www.dropbox.com/list_shared_link_folder_entries', array(
                    "t" => $cookies["t"]["value"],
                    "link_key" => $folder_share_token["linkKey"],
                    "link_type" => $folder_share_token["linkType"],
                    "secure_hash" => $folder_share_token["secureHash"],
                    "sub_path" => ltrim($folder_share_token["subPath"],"/"),
                    "voucher" => $next_request_voucher,
                    "is_xhr" => "true",
                ),$options
            );
             $result = $new_rsp;
             $next_request_voucher = $result["next_request_voucher"];
        }
    }


}

function replace_query_params($url, $params)
{
    $query = parse_url($url, PHP_URL_QUERY);
    parse_str($query, $oldParams);

    if (empty($oldParams)) {
        return rtrim($url, '?') . '?' . http_build_query($params);
    }

    $params = array_merge($oldParams, $params);

    return preg_replace('#\?.*#', '?' . http_build_query($params), $url);
}


function post_form($ch,$url,$data,$options){
   // println("POST FORM....");
    //print_r($data);
//url-ify the data for the POST
    $fields_string=null;
    foreach($data as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
    rtrim($fields_string, '&');

   // println("fields_string: ".$fields_string);

    // $ch = curl_init();

    //set the url, number of POST vars, POST data
    curl_setopt($ch,CURLOPT_URL, $url);

    curl_setopt($ch,CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch,CURLOPT_POST, count($data));
    curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);

    curl_setopt( $ch, CURLOPT_COOKIEJAR, $options['cookieJar'] );
    curl_setopt( $ch, CURLOPT_COOKIEFILE,  $options['cookieFile'] );
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_VERBOSE, true);

   // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);


    //println("=======HEADDER START========");
    //$information = curl_getinfo($ch);
    //print_r($information);
    //println("=======HEADDER END========");

    curl_close($ch);
    //print($response);
    return json_decode($response, true);
}


function get_cookie($url,$options){
    $cookie_text = extractCookies($options['cookieFile']);
    return  $cookie_text;
   // print_r( $cookie_text);
    $cookies = array();
    foreach ($cookie_text as $cookie) {
        if (preg_match('/^Set-Cookie:\s*([^;]+)/', $hdr, $matches)) {
            parse_str($matches[1], $tmp);
            $cookies += $tmp;
        }
    }
    return $cookies;
}

function get_html($ch,$url,$options){
    /* STEP 1. let’s create a cookie file */
   // $ckfile = tempnam ("/tmp", "CURLCOOKIE");
    /* STEP 2. visit the homepage to set the cookie properly */
    $ch = curl_init ();
    curl_setopt($ch,CURLOPT_URL, $url);

    //curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt( $ch, CURLOPT_COOKIEJAR, $options['cookieJar'] );
    curl_setopt( $ch, CURLOPT_COOKIEFILE,  $options['cookieFile'] );
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, 1);

    $output = curl_exec ($ch);

     // Retudn headers seperatly from the Response Body
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($output, 0, $header_size);
    $body = substr($output, $header_size);
    //$cookies = curl_getinfo($ch, CURLINFO_COOKIELIST);

    //println("=======GET RESPONSE HEADDER START========");
    //print($headers);
    //println("=======GET RESPONSE HEADDER END========");
    
    /* STEP 3. visit cookiepage.php */
    #$ch = curl_init ($url);
    #curl_setopt ($ch, CURLOPT_COOKIEFILE, $ckfile); 
    #curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
    #$output = curl_exec ($ch);
    curl_close($ch);
    return $body;
}


function extractCookies($file) {
    $string = file_get_contents($file);
    $lines = explode(PHP_EOL, $string);
    $cookies = array();

    foreach ($lines as $line) {
        $cookie = array();
        //println($line);
        // detect httponly cookies and remove #HttpOnly prefix
        if (substr($line, 0, 10) == '#HttpOnly_') {
            $line = substr($line, 10);
            $cookie['httponly'] = true;
        } else {
            $cookie['httponly'] = false;
        } 

        // we only care for valid cookie def lines
        if( strlen( $line ) > 0 && $line[0] != '#' && substr_count($line, "\t") == 6) {

            // get tokens in an array
            $tokens = explode("\t", $line);

            // trim the tokens
            $tokens = array_map('trim', $tokens);

            // Extract the data
            $cookie['domain'] = $tokens[0]; // The domain that created AND can read the variable.
            $cookie['flag'] = $tokens[1];   // A TRUE/FALSE value indicating if all machines within a given domain can access the variable. 
            $cookie['path'] = $tokens[2];   // The path within the domain that the variable is valid for.
            $cookie['secure'] = $tokens[3]; // A TRUE/FALSE value indicating if a secure connection with the domain is needed to access the variable.

            $cookie['expiration-epoch'] = $tokens[4];  // The UNIX time that the variable will expire on.   
            $cookie['name'] = urldecode($tokens[5]);   // The name of the variable.
            $cookie['value'] = urldecode($tokens[6]);  // The value of the variable.

            // Convert date to a readable format
            $cookie['expiration'] = date('Y-m-d h:i:s', $tokens[4]);

            // Record the cookie.
            $cookies[$cookie['name']] = $cookie;
        }
    }

    return $cookies;
}

