#!/usr/bin/php
<?php

$ary = xmlize(file_get_contents("../gui/coldsim.glade"));

$translate = array();

go_deep($ary['glade-interface']['#']);

$file = "<?php\nif(!defined('IN_SIM'))\n{\n\texit;\n}\n\n" . '$lang = array(' . "\n";

foreach($translate as $data)
{
	$file .= "\t" . '"' . $data[0] . '"' . "\t\t" . '=> "' . str_replace("\n", "\\n", $data[1]) . '",' . "\n";
}
$file .= ');' . "\n?>";
file_put_contents("./strings/en.php", $file);

function go_deep($ary)
{
	global $translate;

	foreach($ary as $k => $v)
	{
		if($k == 'widget')
		{
			foreach($ary[$k] as $size => $data)
			{
				if(isset($data['#']['property']))
				{
					foreach($data['#']['property'] as $property_size => $property_data)
					{
						if(isset($property_data['@']['translatable']) && $property_data['@']['translatable'] == 'yes')
						{
							$translate[] = array($ary[$k][$size]['@']['id'], $property_data['#']);
						}
					}
				}

				if(isset($data['#']['child']))
				{
					foreach($data['#']['child'] as $child_size => $child_data)
					{
						if(isset($child_data['#']))
						{
							go_deep($child_data['#']);
						}
					}
				}
			}
		}
	}
}

function xmlize($data, $WHITE=1) {

    $data = trim($data);
    $vals = $index = $array = array();
    $parser = xml_parser_create();
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, $WHITE);
    if ( !xml_parse_into_struct($parser, $data, $vals, $index) )
    {
die(sprintf("XML error: %s at line %d",
                    xml_error_string(xml_get_error_code($parser)),
                    xml_get_current_line_number($parser)));

    }
    xml_parser_free($parser);

    $i = 0;

    $tagname = $vals[$i]['tag'];
    if ( isset ($vals[$i]['attributes'] ) )
    {
        $array[$tagname]['@'] = $vals[$i]['attributes'];
    } else {
        $array[$tagname]['@'] = array();
    }

    $array[$tagname]["#"] = xml_depth($vals, $i);

    return $array;
}

/*
 *
 * You don't need to do anything with this function, it's called by
 * xmlize. It's a recursive function, calling itself as it goes deeper
 * into the xml levels. If you make any improvements, please let me know.
 *
 *
 */

function xml_depth($vals, &$i) {
    $children = array();

    if ( isset($vals[$i]['value']) )
    {
        array_push($children, $vals[$i]['value']);
    }

    while (++$i < count($vals)) {

        switch ($vals[$i]['type']) {

           case 'open':

                if ( isset ( $vals[$i]['tag'] ) )
                {
                    $tagname = $vals[$i]['tag'];
                } else {
                    $tagname = '';
                }

                if ( isset ( $children[$tagname] ) )
                {
                    $size = sizeof($children[$tagname]);
                } else {
                    $size = 0;
                }

                if ( isset ( $vals[$i]['attributes'] ) )
                {
                    $children[$tagname][$size]['@'] = $vals[$i]["attributes"];

                    if(isset($vals[$i]["attributes"]['translatable']))
                    {
                    	 //   print_r($children[$tagname]);exit;
                    }
                }

                $children[$tagname][$size]['#'] = xml_depth($vals, $i);

            break;


            case 'cdata':
                array_push($children, $vals[$i]['value']);
            break;

            case 'complete':
                $tagname = $vals[$i]['tag'];

                if( isset ($children[$tagname]) )
                {
                    $size = sizeof($children[$tagname]);
                } else {
                    $size = 0;
                }

                if( isset ( $vals[$i]['value'] ) )
                {
                    $children[$tagname][$size]["#"] = $vals[$i]['value'];
                } else {
                    $children[$tagname][$size]["#"] = '';
                }

                if ( isset ($vals[$i]['attributes']) ) {
                    $children[$tagname][$size]['@']
                                             = $vals[$i]['attributes'];
                                             if(isset($vals[$i]["attributes"]['translatable']))
                    {
                    	  //  print_r( $children);exit;
                    }
                }

            break;

            case 'close':
                return $children;
            break;
        }

    }

return $children;

}


/* function by acebone@f2s.com, a HUGE help!
 *
 * this helps you understand the structure of the array xmlize() outputs
 *
 * usage:
 * traverse_xmlize($xml, 'xml_');
 * print '<pre>' . implode("", $traverse_array . '</pre>';
 *
 *
 */

function traverse_xmlize($array, $arrName = "array", $level = 0) {

    foreach($array as $key=>$val)
    {
        if ( is_array($val) )
        {
            traverse_xmlize($val, $arrName . "[" . $key . "]", $level + 1);
        } else {
            $GLOBALS['traverse_array'][] = '$' . $arrName . '[' . $key . '] = "' . $val . "\"\n";
        }
    }

    return 1;

}
?>