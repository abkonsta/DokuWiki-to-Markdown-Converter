<?php
require_once('MarkdownCleanup.php');

// global array cleaning function
function test_null_or_empty($var)
{
	if($var == null)
		return false;

	if(!isset($var))
		return false;

	if(empty(trim($var)))
		return false;

	return true;
}

class DokuWikiToMarkdown {
    // Changed file name from DocuwikiToMarkdownExtra.php to DokuWikiToMarkdown.php to reflect that this code converts to original Markdown syntax now instead of Markdown Extra. (MdlI 2016)
	/**
	 * Convert DokuWiki syntax to markdown
	 *
	 * Author: Mark Stephens
	 * License: BSD
	 */

	/*
	 * KNOWN BUGS:
	 *
	 * - inline code snippets have other inline transforms applied to the code
	 *   body. It needs to be multi-pass:
	 *   - find inline code and replace with a non-translating unique identifier
	 *   - apply other transforms
	 *   - replace unique identifiers with new markup.
	 */

	// These rules are applied whereever inline styling is permitted
	static $inlineRules = array(
		// there are more https://www.dokuwiki.org/wiki:syntax
		'/:!:/'				=>	array("rewrite" => ':warning:'),

		// Headings
		'/^= (.*) =$/'				=>	array("rewrite" => '###### \1'),
		'/^=([^=]*)=*$/'			=>	array("rewrite" => '###### \1'),
		'/^== (.*) ==$/'			=>	array("rewrite" => '##### \1'),
		'/^==([^=]*)=*$/'			=>	array("rewrite" => '##### \1'),
		'/^=== (.*) ===$/'			=>	array("rewrite" => '#### \1'),
		'/^===([^=]*)=*$/'			=>	array("rewrite" => '#### \1'),
		'/^==== (.*) ====$/'		=>	array("rewrite" => '### \1'),
		'/^====([^=]*)=*$/'			=>	array("rewrite" => '### \1'),
		'/^===== (.*) =====$/'		=>	array("rewrite" => '## \1'),
		'/^=====([^=]*)=*$/'		=>	array("rewrite" => '## \1'),
		'/^====== (.*) ======$/'	=>	array("rewrite" => '# \1'),
		'/^======([^=]*)=*$/'		=>	array("rewrite" => '# \1'),

		// Link syntaxes, most specific first
		'/\[\[.*?\|\{\{.*?\}\}\]\]/U' =>
										array("notice" => "Link with image seen, not handled properly"),
		'/\[\[.*?\#.*?\|.*?\]\]/U'	=>	array("notice" => "Link with segment seen, not handled properly"),
		'/\[\[.*?\>.*?\]\]/U'		=>	array("notice" => "interwiki syntax seen, not handled properly"),
		'/\[\[(.*)\]\]/U'			=>	array("call" => "handleLink"),
		
		// Images
		// '/\{\{[^|]*?\}\}/U'	=>	array("call" => "handleImage"),
		'/\{\{\s?\:?(.+)\}\}/i'	=>	array("call" => "handleLinksAndImages"),

		// the image function handles this ^^
		// '/\{\{(.*)\}\}/U'			=>	array("call" => "handleLinkMedia"),

		// Inline code.
		'/\'\'(.+)\'\'/U'			=>	array("rewrite" => '``\1``'),

		// Misc checks
		// '/^\d*\.\s/'				=>	array("notice" => "Possible numbered list item that is not docuwiki format, not handled"),
		'/^=+\s*.*$/'				=>	array("notice" => "Line starts with an =. Possibly an untranslated heading. Check for = in the heading text"),
		// <x@y.xom>						email

                // extra rules for liquibase wiki
                // remove liquibase.org
                //'/]\(http:\/\/liquibase\.org\/([^\)]*)\)/'	=>	array("rewrite" => '](\1)'),

                // add .html and site template variables
                //'/]\(([^\)]*)\)/'				=>	array("rewrite" => ']({{ site.url }}/{{ page.lang }}/\1.html)')
	);

	// Contains the name of current input file being processed.
	var $fileName;

	// Contains the line number of the input file currently being processed.
	var $lineNumber;

	// Used when parsing lists. Has one of three values:
	// ""			not processing a list.
	// "unordered"	processing items in an unordered list
	// "ordered"	processing items in an ordered list
	var $listItemType;

	// Counter for ordered lists.
	var $listItemCount;

	function convert($s) {
		$lines = $this->getLines($s);

		$output = "";
		$lineMode = "text";

		$this->listItemType = "";
		$this->lineNumber = 0;

		foreach ($lines as $line) {
			$this->lineNumber++;

			$prevLineMode = $lineMode;

			// Determine if the line mode is changing
			// Please note: this converts DokuWiki code to Markdown fences ``` code block syntax (4 space indentation), not Markdown Extra. (MdlI 2016)
			$tl = trim($line);
			if ($lineMode != "code" && preg_match('/(.+<\/code>){2,}/', $tl)) {
			    $lineParts = preg_split('/\<(code|file)(|\s([a-zA-Z0-9])*)\>|<\/(code|file)>/',$line);
			    $line = implode('``',$lineParts);
			}
			else if ($lineMode != "code" && preg_match('/\<(code|file)(|\s([a-zA-Z0-9])*)\>/U', $tl, $matches) && preg_match('/<\/(code|file)>/', $tl)) {
			    $line = rtrim($line);
			    $output .= ltrim(substr($line,0,strpos($line, "<")));
			    $line .= (isset($matches[2])) ? "``` " . $matches[2] : "```";
			    $line = "\n".substr(substr($line,strpos($line, ">") + 1),0,(strpos($line, "<\/code>")||strpos($line, "<\/file>")) - 7);
			    $line .= "\n```";
			    $lineMode = "end_of_code";
			}
			else if ($lineMode != "code" && preg_match('/\<(code|file)(|\s([a-zA-Z0-9])*)\>/U', $tl, $matches)) {
			    $output .= ltrim(substr($line,0,strpos($line, "<")));
				$line = substr($line,strpos($line, ">") + 1);
				$line .= (isset($matches[2])) ? "```" . $matches[2] : "```";
				$lineMode = "code";
			}
			else if ($lineMode != "code" && preg_match('/\<(code|file)(\s([^|>]+)*\|([^>]+))\>/U', $tl, $matches)) {
				$output .= ltrim(substr($line,0,strpos($line, "<")));
				$output .= "[comment]: # (" . $matches[4] . ")\n";
				$line = substr($line,strpos($line, ">") + 1);
				$line .= (isset($matches[3])) ? "```" . $matches[3] : "```";
				$lineMode = "code";
			}
			else if ($lineMode == "code" && preg_match('/<\/(code|file)>/', $tl)) {
			    $line = rtrim($line);
				$line = substr($line,0,(strpos($line, "<\/code>")||strpos($line, "<\/file>")) - 7);
				$line = "```";
				$lineMode = "end_of_code";
			}
			else if ($lineMode == "text" && strlen($tl) > 0 && ($tl[0] == "^")) {
				// first char is a ^ so its the start of a table. In table mode we
				// just accumulate table rows in $table, and render when
				// we switch out of table mode so we can do column widths right.
				$lineMode = "table_head";
				$table = array();
			}
			else if ($lineMode == "text" && strlen($tl) > 0 && substr_count($tl, "|") > 2) {
				// alternative table opening - writer used | instead of ^ for header
				// so we pretend that the correct ^ were used instead so that the code below handles it
				$lineMode = "table_head";
				$line = strtr($line, '|', '^');
				$table = array();
			}
			else if ($lineMode == "table_head" && strlen($tl) > 0 && ($tl[0] == "|")) {
				// first char is a ^ so its the start of a table. In table mode we
				// just accumulate table rows in $table, and render when
				// we switch out of table mode so we can do column widths right.
				$lineMode = "table_body";
				$table = array();

			}
			else if ($lineMode == "table_body" && ($tl == "" ||
				($tl[0] != "^" && $tl[0] != "|"))) {
				//$output .= "</table>\n";
				$lineMode = "text";
			}

			// perform mode-specific translations
			switch ($lineMode) {
			    case "end_of_code":
			        //$line = "        ".$line."\n";
			        $lineMode = "text";
			        break;
				case "text":
					// $line = ltrim($line);
					$line = $this->convertInlineMarkup($line);
					$line = $this->convertListItems($line);
					break;
				case "code":
					//$line = "        ".$line;
					break;
				case "table_head":

					// $output .= "<table>\n  <tr>";

					// Grab this line, break it up and add it to $table after
					// performing inline transforms on each cell.
					$parts = explode("^", $this->convertInlineMarkup($line));

					// Remove 1st and last element from $parts, as they are
					// always empty.
					// Remove last element:
					array_pop($parts);
					// Remove 1st element - like this, as it's faster.
					$parts = array_reverse($parts); array_pop($parts); $parts = array_reverse($parts);

					$output .= "|";
					for ($i=0; $i < count($parts); $i++) {
						// if (strlen(trim($parts[$i])) > 0) {
							// $parts[$i] = "<th>" . trim($parts[$i]) . "</th>";
							$parts[$i] = "   " . trim($parts[$i]) . "   |";
							$output .= $parts[$i];
						// } else {
						// 	echo "0\n";
						// }
					}
					$output .= "\n";

					$output .= "|";
					for ($i=0; $i < count($parts); $i++) {
						if (strlen(trim($parts[$i])) > 0) {
							$output .= " " . str_repeat("-", strlen(trim($parts[$i]))) . " |";
						}
					}
					$output .= "\n";
					// $output .= "</tr>\n";

					break;
				case "table_body":

					// $output .= "  <tr>";
					$output .= "|";

					// Grab this line, break it up and add it to $table after
					// performing inline transforms on each cell.
					$parts = explode("|", $this->convertInlineMarkup($line));

					for ($i=1; $i < (count($parts) - 1); $i++) {
						if ($parts[$i] != "") {
							$colspan = 1;
							$j = $i;
							while (($parts[$j + 1] == "") && ($j < (count($parts) - 2))) {
								$colspan++;
								$j++;
							}
							if ($colspan == 1) {
								$colspanatt = "";
							} else {
								$colspanatt = " colspan=\"" . $colspan . "\"";
							}

							// $parts[$i] = "<td" . $colspanatt . ">" . trim($parts[$i]) . "</td>";
							$parts[$i] = " " . trim($parts[$i]) . " |";
							$output .= $parts[$i];
						}
			        }
					$output .= "\n";
					//$output .= "</tr>\n";

					break;
			}

			if ($lineMode != "table_body" && $lineMode != "table_head") $output .= $line . "\n";
		}

		$cleanup = new MarkdownCleanup();

		$output = $cleanup->process($output);

		return $output;
	}

	static $underline = "";

	function renderTable($table) {
	/*
		// get a very big underline
		//if (!self::$underline) for ($i = 0; $i < 100; $i++) self::$underline .= "----------";

		// Calculate maximum columns widths
		$widths = array();
		foreach ($table as $row) {
			for ($i = 0; $i < count($row); $i++) {
				if (!isset($widths[$i])) $widths[$i] = 0;
				if (strlen($row[$i]) > $widths[$i]) $widths[$i] = strlen($row[$i]);
			}
		}

		$s = "";
		$headingRow = true;
		foreach ($table as $row) {
			for ($i = 0; $i < count($row); $i++) {
				$s .= $row[$i];
			}
			$s .= "</tr>\n";

			//if ($headingRow) {
				// underlines of the length of the column headings
				//for ($i = 0; $i < count($row); $i++) {
				//	if ($i > 0) $s .= " | ";
				//	$s .= str_pad(substr(self::$underline, 0, strlen($row[$i])), $widths[$i]);
				//}
			//	$s .= "\n";
			//}

			$headingRow = false;
		}

		return $s;*/

	}

	// Perform inline translations.
	function convertInlineMarkup($line) {
		// Apply regexp rules
		foreach (self::$inlineRules as $from => $to) {
			if (isset($to["rewrite"]))
				$line = preg_replace($from, $to["rewrite"], $line);
			if (isset($to["notice"]) && preg_match($from, $line))
				$this->notice($to["notice"]);
			if (isset($to["call"]) && preg_match_all($from, $line, $matches))
				$line = call_user_func_array(array($this, $to["call"]), array($line, $matches));
		}

		return $line;
	}

	// Handle transforming list items:
	// __* text		[unordered list item] =>
	// __-			[ordered list item] =>
	// Doesn't handle nested lists, but will emit a notice.
	function convertListItems($s) {
		if ($s == "") return $s;

		if (substr($s, 0, 2) != "  " && $s[0] != "\t" && trim($s) != "") {
			// Termination condition for a list is that the text is not
			// indented.
			$this->listItemType = "";
			return $s;
		}

		if (substr($s, 0, 3) == "  *") {
			$this->listItemType = "unordered";
			$s = substr($s, 2); // remove leading space

			// force exactly 2 spaces after bullet to make things line up nicely.
            // Mathias: Why?
			if (substr($s, 1, 1) != " ") $s = "\n* " . substr($s, 1);
			if (substr($s, 2, 1) != " ") $s = "\n* " . substr($s, 2);
		}
		else if (substr($s, 0, 3) == "  -") {
			if ($this->listItemType != "ordered") $this->listItemCount = 1;
			$this->listItemType = "ordered";
			$s = " " . $this->listItemCount . ". " . substr($s, 3);
			$this->listItemCount++;
		}
		else if (substr($s, 0, 3) == "   ") {
			$t = trim($s);
			//if ($t && ($t[0] == "*" || $t[0] == "-"))
			//	$this->notice("Possible nested indent, which isn't handled");
		}
		else if (substr($s, 0, 2) == "  ") {
			// we're a list, but this line is not the start of a point, so
			// indent it. We indent by 4 spaces, which is required for additional
			// paragraphs in an item in markdown. We only have to add 2, because
			// there are already 2 there.
			$s = "  " . $s;
		}

		return $s;
	}

	// Called by a rule that match links with [[ ]]. $line is the line to munge.
	// $matchArgs are passed from preg_match_all; there are always two entries
	// and $matchArgs[0] is the source link including [[ and ]], and is what
	// we can replace with a link.
	//
	// some variants:
	// - [[http://doc.silverstripe.org/doku.php?id=contributing#reporting_security_issues|contributing guidelines]]
	//   has a # fragment, but part of the URL, not ahead of the URL as specified in docuwiki
	// - [[http://url|{{http://url/file.png}}]] (x1)
	// - [[recipes:forms]]
	// - [[recipes:forms|our form recipes]]
	// - [[tutorial:3-forms#showing_the_poll_results|tutorial:3-forms: Showing the poll results]]
	// - [[directory-structure#module_structure|directory structure guidelines]]
	// - [[:themes:developing]]
	// - [[GSoc:2007:i18n]]
	// - [[:Requirements]]
	// - [[#ComponentSet]]
	// - [[ModelAdmin#searchable_fields]]
	// - [[irc:our weekly core discussions on IRC]]
	// - [[#documentation|documentation]]
	// - [[community run third party websites]]
	// - [[requirements#including_inside_template_files|Includes in Templates]]
	function handleLinkBase($line, $matchArgs, $media=false) {
		foreach ($matchArgs[0] as $match) {
			$link = substr($match, 2, -2);
			$parts = explode("|", $link);

			// trim spaces especially the leading spaces
			$parts[0] = trim($parts[0]);

			if ($media)
				$media = "/media";
			else
				$media = "";
			if (count($parts) == 1) $replacement = "[" . $parts[0] . "](" .$media. $this->translateInternalLink($parts[0]) . ")";
			else {
				if (strpos($parts[1], "{{")) $this->notice("Image inside link not translated, requires manual editing");
				if ($parts[1] == '')
					// {{media}}
					$replacement = "[" . $parts[0] . "](/media" . $this->translateInternalLink($parts[0]) . ")";
					// not working with relative link
					//$replacement = "</media" . $this->translateInternalLink($parts[0]) . ">";
				else
					$replacement = "[" . $parts[1] . "](" .$media. $this->translateInternalLink($parts[0]) . ")";
			}

			$line = str_replace($match, $replacement, $line);
		}

		return $line;
	}
	// {{media|label}}
	function handleLinkMedia($line, $matchArgs, $media=false) {
		return $this->handleLinkBase($line, $matchArgs, true);
	}
	// {{media|label}}
	function handleLink($line, $matchArgs, $media=false) {
		return $this->handleLinkBase($line, $matchArgs, false);
	}

	// Called by rules that match image references with {{ }}
	// Specific cases that we handle are:
	// - {{:file.png|:file.png}}
	// - {{http://something/file.png}}
	// - {{http://something/file.png|display}}
	// - {{tutorial:file.png}}
	
	/*
	function handleImage($line, $matchArgs) {
		foreach ($matchArgs[0] as $match) {
			$link = substr($match, 2, -2);
			$parts = explode("|", $link);

			if (count($parts) == 1) $replacement = "![" . (($parts[0] == "")?($this->translateInternalLink($parts[0])):($parts[0])) . "](" . $this->translateInternalLink($parts[0]) . " \"" . (($parts[0] == "")?($this->translateInternalLink($parts[0])):($parts[0])) . "\")";
			else $replacement = "![" . (($parts[1] == "")?($this->translateInternalLink($parts[0])):($parts[1])) . "](" . $this->translateInternalLink($parts[0]) . " \"" . (($parts[1] == "")?($this->translateInternalLink($parts[0])):($parts[1])) . "\")";

			$line = str_replace($match, $replacement, $line);
		}

		return $line;
	}
	*/

	function handleLinksAndImages($line, $matchArgs) {
		foreach($matchArgs[0] as $match) {
			$result = $matchArgs[1][0];
			$descr = "";
			$file = "";
			$size = "";
			$image_pfx = "";
			$path_pfx = "/.attachments/";

			$with_descr = array_filter(explode("|", $result), "test_null_or_empty");
			if(count($with_descr) > 0)
				$result = $with_descr[0];

			if(count($with_descr) > 1)
				$descr = $with_descr[1];

			$with_size = array_filter(explode("?", $result), "test_null_or_empty");
			if(count($with_size) > 0)
				$result = $with_size[0];

			if(count($with_size) > 1)
				$size = " =" . trim($with_size[1]) . "x";
			
			$file = trim($result);
			$file = str_replace(" ", "_", $file); 
			if($file[0] == ':')
				$file = substr($file, 1);

			$descr = trim($descr);

			if(strlen($descr) < 1)
				$descr = $file;

			$file_parts = pathinfo($file);

			// figure out if this is an image or other media
			switch($file_parts['extension'])
			{
			    case "jpg":
			    case "jpeg":
			    case "gif":
			    case "png":
			    case "bmp":
			    	$image_pfx = "!";
			    	break;
			}

			// figure out of this is external or internal content
			if(substr($file, 0, 4) === 'http')
				$path_pfx = "";

			$replacement = "$image_pfx" . "[" . $descr . "](" . $path_pfx . $file . $size . ")";
			$line = str_replace($match, $replacement, $line);
		}

		return $line;	
	}

	// Convert an internal docuwiki link, which is basically some combination
	// of identifiers with ":" separators ("namespaces"), which are really
	// folders. The input is any link. This only alters internal links.
	function translateInternalLink($s) {
		if (substr($s, 0, 5) == "http:" || substr($s, 0, 6) == "https") return $s;
		
		//if (strpos($s, ':') !== false) {
			$s = strtolower($s);
			$s = str_replace("(", "", $s);
			$s = str_replace(")", "", $s);
			$s = str_replace(",", "", $s);
			$s = strtr($s, " ", "_");
		//}

		return str_replace(":", "/", $s);
	}

	function notice($message) {
		echo "Notice: {$this->fileName} (line {$this->lineNumber}): $message\n";
	}

	// Return an array of lines from s, ensuring we handle different end-of-line
	// variations
	function getLines($s) {
		// Ensure that we only have a single \n at the end of each line.
		$s = str_replace("\r\n", "\n", $s);
		$s = str_replace("\r", "\n", $s);
		return explode("\n", $s);
	}

	// Convert a docuwiki file in the input directory and called
	// $filename, and re-created it in the output directory, translated
	// to markdown extra.
	function convertFile($inputFile, $outputFile = null, $flags = 0) {
		$this->fileName = $inputFile;
		$s = file_get_contents($inputFile);
		$s = $this->convert($s);

		if($outputFile) {
			if (file_put_contents($outputFile, $s, $flags) === FALSE)
				echo "Could not write file {$outputFile}\n";
		}

		return $s;
	}
}

?>
