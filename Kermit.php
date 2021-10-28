<?php
#
# Kermit - Typography enhancer for web projects
# Copyright (c) 2020-2021 Adriano Santi
# <https://adrianosanti.com/>
#
# Kermit is a blatant copy of work by
# Michel Fortin <https://michelf.ca/>
# and John Gruber <https://daringfireball.net/>

namespace Ribbit;


#
# Kermit Parser Class
#

class Kermit {

	### Version ###

	const  KERMITLIB_VERSION  =  "1.0.0";


	### Presets

	# Kermit does nothing at all
	const  ATTR_DO_NOTHING             =  0;
	# "--" for em-dashes; no en-dash support
	const  ATTR_EM_DASH                =  1;
	# "---" for em-dashes; "--" for en-dashes
	const  ATTR_LONG_EM_DASH_SHORT_EN  =  2;
	# "--" for em-dashes; "---" for en-dashes
	const  ATTR_SHORT_EM_DASH_LONG_EN  =  3;
	# "--" for em-dashes; "---" for en-dashes
	const  ATTR_STUPEFY                = -1;
	# Convert international/special characters
	const  ATTR_INTL                   =  4;

	# The default preset: ATTR_EM_DASH
	const  ATTR_DEFAULT  =  Kermit::ATTR_INTL;


	### Standard Function Interface ###

	public static function defaultTransform($text, $attr = Kermit::ATTR_DEFAULT) {
	#
	# Initialize the parser and return the result of its transform method.
	# This will work fine for derived classes too.
	#
		# Take parser class on which this function was called.
		$parser_class = \get_called_class();

		# try to take parser from the static parser list
		static $parser_list;
		$parser =& $parser_list[$parser_class][$attr];

		# create the parser if not already set
		if (!$parser)
			$parser = new $parser_class($attr);

		# Transform text using parser.
		return $parser->transform($text);
	}


	### Configuration Variables ###

	# Partial regex for matching tags to skip
	public $tags_to_skip = 'pre|code|kbd|script|style|math';

	# Options to specify which transformations to make:
	public $do_nothing   = 0; # disable all transforms
	public $do_quotes    = 0;
	public $do_backticks = 0; # 1 => double only, 2 => double & single
	public $do_dashes    = 0; # 1, 2, or 3 for the three modes described above
	public $do_ellipses  = 0;
	public $do_stupefy   = 0;
	public $do_intl      = 0;
	public $convert_quot = 0; # should we translate &quot; entities into normal quotes?

	# Smart quote characters:
	# Opening and closing smart double-quotes.
	public $smart_doublequote_open  = '&#8220;';
	public $smart_doublequote_close = '&#8221;';
	public $smart_singlequote_open  = '&#8216;';
	public $smart_singlequote_close = '&#8217;'; # Also apostrophe.

	# ``Backtick quotes''
	public $backtick_doublequote_open  = '&#8220;'; // replacement for ``
	public $backtick_doublequote_close = '&#8221;'; // replacement for ''
	public $backtick_singlequote_open  = '&#8216;'; // replacement for `
	public $backtick_singlequote_close = '&#8217;'; // replacement for ' (also apostrophe)

	# Other punctuation
	public $em_dash = '&#8212;';
	public $en_dash = '&#8211;';
	public $ellipsis = '&#8230;';

	### Parser Implementation ###

	public function __construct($attr = Kermit::ATTR_DEFAULT) {
	#
	# Initialize a parser with certain attributes.
	#
	# Parser attributes:
	# 0 : do nothing
	# 1 : set all except special chars
	# 2 : set all except special chars, using old school en- and em- dash shortcuts
	# 3 : set all except special chars, using inverted old school en and em- dash shortcuts
	# 4 : set all including special chars, using old school en- and em- dash shortcuts
	# 
	# q : quotes
	# b : backtick quotes (``double'' only)
	# B : backtick quotes (``double'' and `single')
	# d : dashes
	# D : old school dashes
	# i : inverted old school dashes
	# e : ellipses
	# w : convert &quot; entities to " for Dreamweaver users
	#
		if ($attr == "0") {
			$this->do_nothing   = 1;
		}
		else if ($attr == "1") {
			# Do everything, turn all options on.
			$this->do_quotes    = 1;
			$this->do_backticks = 1;
			$this->do_dashes    = 1;
			$this->do_ellipses  = 1;
		}
		else if ($attr == "2") {
			# Do everything, turn all options on, use old school dash shorthand.
			$this->do_quotes    = 1;
			$this->do_backticks = 1;
			$this->do_dashes    = 2;
			$this->do_ellipses  = 1;
		}
		else if ($attr == "3") {
			# Do everything, turn all options on, use inverted old school dash shorthand.
			$this->do_quotes    = 1;
			$this->do_backticks = 1;
			$this->do_dashes    = 3;
			$this->do_ellipses  = 1;
		}
		else if ($attr = "4") {
			$this->do_quotes    = 1;
			$this->do_backticks = 1;
			$this->do_dashes    = 2;
			$this->do_ellipses  = 1;
			$this->do_intl      = 1;
		}
		else if ($attr == "-1") {
			# Special "stupefy" mode.
			$this->do_stupefy   = 1;
		}
		else {
			$chars = preg_split('//', $attr);
			foreach ($chars as $c){
				if      ($c == "q") { $this->do_quotes    = 1; }
				else if ($c == "b") { $this->do_backticks = 1; }
				else if ($c == "B") { $this->do_backticks = 2; }
				else if ($c == "d") { $this->do_dashes    = 1; }
				else if ($c == "D") { $this->do_dashes    = 2; }
				else if ($c == "i") { $this->do_dashes    = 3; }
				else if ($c == "e") { $this->do_ellipses  = 1; }
				else if ($c == "w") { $this->convert_quot = 1; }
				else {
					# Unknown attribute option, ignore.
				}
			}
		}
	}

	public function transform($text) {

		if ($this->do_nothing) {
			return $text;
		}

		$tokens = $this->tokenizeHTML($text);
		$result = '';
		$in_pre = 0;  # Keep track of when we're inside <pre> or <code> tags.

		$prev_token_last_char = ""; # This is a cheat, used to get some context
									# for one-character tokens that consist of 
									# just a quote char. What we do is remember
									# the last character of the previous text
									# token, to use as context to curl single-
									# character quote tokens correctly.

		foreach ($tokens as $cur_token) {
			if ($cur_token[0] == "tag") {
				# Don't mess with quotes inside tags.
				$result .= $cur_token[1];
				if (preg_match('@<(/?)(?:'.$this->tags_to_skip.')[\s>]@', $cur_token[1], $matches)) {
					$in_pre = isset($matches[1]) && $matches[1] == '/' ? 0 : 1;
				}
			} else {
				$t = $cur_token[1];
				$last_char = substr($t, -1); # Remember last char of this token before processing.
				if (! $in_pre) {
					$t = $this->educate($t, $prev_token_last_char);
				}
				$prev_token_last_char = $last_char;
				$result .= $t;
			}
		}

		return $result;
	}


	function decodeEntitiesInConfiguration() {
	#
	#   Utility function that converts entities in configuration variables to
	#   UTF-8 characters.
	#
		$output_config_vars = array(
			'smart_doublequote_open',
			'smart_doublequote_close',
			'smart_singlequote_open',
			'smart_singlequote_close',
			'backtick_doublequote_open',
			'backtick_doublequote_close',
			'backtick_singlequote_open',
			'backtick_singlequote_close',
			'em_dash',
			'en_dash',
			'ellipsis',
		);
		foreach ($output_config_vars as $var) {
			$this->$var = html_entity_decode($this->$var);
		}
	}


	protected function educate($t, $prev_token_last_char) {
		$t = $this->processEscapes($t);

		if ($this->convert_quot) {
			$t = preg_replace('/&quot;/', '"', $t);
		}

		if ($this->do_dashes) {
			if ($this->do_dashes == 1) $t = $this->educateDashes($t);
			if ($this->do_dashes == 2) $t = $this->educateDashesOldSchool($t);
			if ($this->do_dashes == 3) $t = $this->educateDashesOldSchoolInverted($t);
		}

		if ($this->do_ellipses) $t = $this->educateEllipses($t);

		# Note: backticks need to be processed before quotes.
		if ($this->do_backticks) {
			$t = $this->educateBackticks($t);
			if ($this->do_backticks == 2) $t = $this->educateSingleBackticks($t);
		}

		if ($this->do_quotes) {
			if ($t == "'") {
				# Special case: single-character ' token
				if (preg_match('/\S/', $prev_token_last_char)) {
					$t = $this->smart_singlequote_close;
				}
				else {
					$t = $this->smart_singlequote_open;
				}
			}
			else if ($t == '"') {
				# Special case: single-character " token
				if (preg_match('/\S/', $prev_token_last_char)) {
					$t = $this->smart_doublequote_close;
				}
				else {
					$t = $this->smart_doublequote_open;
				}
			}
			else {
				# Normal case:
				$t = $this->educateQuotes($t);
			}
		}

		if ($this->do_intl) {
			$t = $this->educateIntl($t);
		}

		if ($this->do_stupefy) $t = $this->stupefyEntities($t);
		
		return $t;
	}


	protected function educateQuotes($_) {
	#
	#   Parameter:  String.
	#
	#   Returns:    The string, with "educated" curly quote HTML entities.
	#
	#   Example input:  "Isn't this fun?"
	#   Example output: &#8220;Isn&#8217;t this fun?&#8221;
	#
		$dq_open  = $this->smart_doublequote_open;
		$dq_close = $this->smart_doublequote_close;
		$sq_open  = $this->smart_singlequote_open;
		$sq_close = $this->smart_singlequote_close;
	
		# Make our own "punctuation" character class, because the POSIX-style
		# [:PUNCT:] is only available in Perl 5.6 or later:
		$punct_class = "[!\"#\\$\\%'()*+,-.\\/:;<=>?\\@\\[\\\\\]\\^_`{|}~]";

		# Special case if the very first character is a quote
		# followed by punctuation at a non-word-break. Close the quotes by brute force:
		$_ = preg_replace(
			array("/^'(?=$punct_class\\B)/", "/^\"(?=$punct_class\\B)/"),
			array($sq_close,                 $dq_close), $_);

		# Special case for double sets of quotes, e.g.:
		#   <p>He said, "'Quoted' words in a larger quote."</p>
		$_ = preg_replace(
			array("/\"'(?=\w)/",     "/'\"(?=\w)/"),
			array($dq_open.$sq_open, $sq_open.$dq_open), $_);

		# Special case for decade abbreviations (the '80s):
		$_ = preg_replace("/'(?=\\d{2}s)/", $sq_close, $_);

		$close_class = '[^\ \t\r\n\[\{\(\-]';
		$dec_dashes = '&\#8211;|&\#8212;';

		# Get most opening single quotes:
		$_ = preg_replace("{
			(
				\\s          |   # a whitespace char, or
				&nbsp;      |   # a non-breaking space entity, or
				--          |   # dashes, or
				&[mn]dash;  |   # named dash entities
				$dec_dashes |   # or decimal entities
				&\\#x201[34];    # or hex
			)
			'                   # the quote
			(?=\\w)              # followed by a word character
			}x", '\1'.$sq_open, $_);
		# Single closing quotes:
		$_ = preg_replace("{
			($close_class)?
			'
			(?(1)|          # If $1 captured, then do nothing;
			  (?=\\s | s\\b)  # otherwise, positive lookahead for a whitespace
			)               # char or an 's' at a word ending position. This
							# is a special case to handle something like:
							# \"<i>Custer</i>'s Last Stand.\"
			}xi", '\1'.$sq_close, $_);

		# Any remaining single quotes should be opening ones:
		$_ = str_replace("'", $sq_open, $_);


		# Get most opening double quotes:
		$_ = preg_replace("{
			(
				\\s          |   # a whitespace char, or
				&nbsp;      |   # a non-breaking space entity, or
				--          |   # dashes, or
				&[mn]dash;  |   # named dash entities
				$dec_dashes |   # or decimal entities
				&\\#x201[34];    # or hex
			)
			\"                   # the quote
			(?=\\w)              # followed by a word character
			}x", '\1'.$dq_open, $_);

		# Double closing quotes:
		$_ = preg_replace("{
			($close_class)?
			\"
			(?(1)|(?=\\s))   # If $1 captured, then do nothing;
							   # if not, then make sure the next char is whitespace.
			}x", '\1'.$dq_close, $_);

		# Any remaining quotes should be opening ones.
		$_ = str_replace('"', $dq_open, $_);

		return $_;
	}


	protected function educateBackticks($_) {
	#
	#   Parameter:  String.
	#   Returns:    The string, with ``backticks'' -style double quotes
	#               translated into HTML curly quote entities.
	#
	#   Example input:  ``Isn't this fun?''
	#   Example output: &#8220;Isn't this fun?&#8221;
	#

		$_ = str_replace(array("``", "''",),
						 array($this->backtick_doublequote_open,
							   $this->backtick_doublequote_close), $_);
		return $_;
	}


	protected function educateSingleBackticks($_) {
	#
	#   Parameter:  String.
	#   Returns:    The string, with `backticks' -style single quotes
	#               translated into HTML curly quote entities.
	#
	#   Example input:  `Isn't this fun?'
	#   Example output: &#8216;Isn&#8217;t this fun?&#8217;
	#

		$_ = str_replace(array("`",       "'",),
						 array($this->backtick_singlequote_open,
							   $this->backtick_singlequote_close), $_);
		return $_;
	}


	protected function educateDashes($_) {
	#
	#   Parameter:  String.
	#
	#   Returns:    The string, with each instance of "--" translated to
	#               an em-dash HTML entity.
	#

		$_ = str_replace('--', $this->em_dash, $_);
		return $_;
	}


	protected function educateDashesOldSchool($_) {
	#
	#   Parameter:  String.
	#
	#   Returns:    The string, with each instance of "--" translated to
	#               an en-dash HTML entity, and each "---" translated to
	#               an em-dash HTML entity.
	#

		#                      em              en
		$_ = str_replace(array("---",          "--",),
						 array($this->em_dash, $this->en_dash), $_);
		return $_;
	}


	protected function educateDashesOldSchoolInverted($_) {
	#
	#   Parameter:  String.
	#
	#   Returns:    The string, with each instance of "--" translated to
	#               an em-dash HTML entity, and each "---" translated to
	#               an en-dash HTML entity.
	#

		#                      en              em
		$_ = str_replace(array("---",          "--",),
						 array($this->en_dash, $this->em_dash), $_);
		return $_;
	}

	protected function educateIntl($_) {
	#
	#	Parameter:	String.
	#
	#	Returns: The string, with special characters converted to their
	#			 respective HTML entities.
	#
		
		$_ = str_replace(array('$','%','^','~',' ','¡','¢','£','¤','¥','¦','§','¨','©','ª','«','¬','­','®','¯','°','±','²','³','´','µ','¶','·','¸','¹','º','»','¼','½','¾','¿','À','Á','Â','Ã','Ä','Å','Æ','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ð','Ñ','Ò','Ó','Ô','Õ','Ö','×','Ø','Ù','Ú','Û','Ü','Ý','Þ','ß','à','á','â','ã','ä','å','æ','ç','è','é','ê','ë','ì','í','î','ï','ð','ñ','ò','ó','ô','õ','ö','÷','ø','ù','ú','û','ü','ý','þ','ÿ','Ā','ā','Ă','ă','Ą','ą','Ć','ć','Ĉ','ĉ','Ċ','ċ','Č','č','Ď','ď','Đ','đ','Ē','ē','Ĕ','ĕ','Ė','ė','Ę','ę','Ě','ě','Ĝ','ĝ','Ğ','ğ','Ġ','ġ','Ģ','ģ','Ĥ','ĥ','Ħ','ħ','Ĩ','ĩ','Ī','ī','Ĭ','ĭ','Į','į','İ','ı','Ĳ','ĳ','Ĵ','ĵ','Ķ','ķ','ĸ','Ĺ','ĺ','Ļ','ļ','Ľ','ľ','Ŀ','ŀ','Ł','ł','Ń','ń','Ņ','ņ','Ň','ň','ŉ','Ŋ','ŋ','Ō','ō','Ŏ','ŏ','Ő','ő','Œ','œ','Ŕ','ŕ','Ŗ','ŗ','Ř','ř','Ś','ś','Ŝ','ŝ','Ş','ş','Š','š','Ţ','ţ','Ť','ť','Ŧ','ŧ','Ũ','ũ','Ū','ū','Ŭ','ŭ','Ů','ů','Ű','ű','Ų','ų','Ŵ','ŵ','Ŷ','ŷ','Ÿ','Ź','ź','Ż','ż','Ž','ž','ſ','ƀ','Ɓ','Ƃ','ƃ','Ƅ','ƅ','Ɔ','Ƈ','ƈ','Ɖ','Ɗ','Ƌ','ƌ','ƍ','Ǝ','Ə','Ɛ','Ƒ','ƒ','Ɠ','Ɣ','ƕ','Ɩ','Ɨ','Ƙ','ƙ','ƚ','ƛ','Ɯ','Ɲ','ƞ','Ɵ','Ơ','ơ','Ƣ','ƣ','Ƥ','ƥ','Ʀ','Ƨ','ƨ','Ʃ','ƪ','ƫ','Ƭ','ƭ','Ʈ','Ư','ư','Ʊ','Ʋ','Ƴ','ƴ','Ƶ','ƶ','Ʒ','Ƹ','ƹ','ƺ','ƻ','Ƽ','ƽ','ƾ','ƿ','ǀ','ǁ','ǂ','ǃ','Ǆ','ǅ','ǆ','Ǉ','ǈ','ǉ','Ǌ','ǋ','ǌ','Ǎ','ǎ','Ǐ','ǐ','Ǒ','ǒ','Ǔ','ǔ','Ǖ','ǖ','Ǘ','ǘ','Ǚ','ǚ','Ǜ','ǜ','ǝ','Ǟ','ǟ','Ǡ','ǡ','Ǣ','ǣ','Ǥ','ǥ','Ǧ','ǧ','Ǩ','ǩ','Ǫ','ǫ','Ǭ','ǭ','Ǯ','ǯ','ǰ','Ǳ','ǲ','ǳ','Ǵ','ǵ','Ƕ','Ƿ','Ǹ','ǹ','Ǻ','ǻ','Ǽ','ǽ','Ǿ','ǿ','Ȁ','ȁ','Ȃ','ȃ','Ȅ','ȅ','Ȇ','ȇ','Ȉ','ȉ','Ȋ','ȋ','Ȍ','ȍ','Ȏ','ȏ','Ȑ','ȑ','Ȓ','ȓ','Ȕ','ȕ','Ȗ','ȗ','Ș','ș','Ț','ț','Ȝ','ȝ','Ȟ','ȟ','Ƞ','ȡ','Ȣ','ȣ','Ȥ','ȥ','Ȧ','ȧ','Ȩ','ȩ','Ȫ','ȫ','Ȭ','ȭ','Ȯ','ȯ','Ȱ','ȱ','Ȳ','ȳ','ȴ','ȵ','ȶ','ȷ','ȸ','ȹ','Ⱥ','Ȼ','ȼ','Ƚ','Ⱦ','ȿ','ɀ','Ɂ','ɂ','Ƀ','Ʉ','Ʌ','Ɇ','ɇ','Ɉ','ɉ','Ɋ','ɋ','Ɍ','ɍ','Ɏ','ɏ','ʇ','ʈ','ʉ','ʊ','ʋ','ʌ','ʍ','ʎ','ʏ','ʐ','ʑ','ʒ','ʓ','ʔ','ʕ','ʖ','ʗ','ʘ','ʙ','ʚ','ʛ','ʜ','ʝ','“','”','’'),
			array('&#36;','&#37;','&#94;','&#126;',' ','&iexcl;','&cent;','&pound;','&curren;','&yen;','&brvbar;','&sect;','&uml;','&copy;','&ordf;','&laquo;','&not;','&shy;','&reg;','&macr;','&deg;','&plusmn;','&sup2;','&sup3;','&acute;','&micro;','&para;','&middot;','&cedil;','&sup1;','&ordm;','&raquo;','&frac14;','&frac12;','&frac34;','&iquest;','&Agrave;','&Aacute;','&Acirc;','&Atilde;','&Auml;','&Aring;','&AElig;','&Ccedil;','&Egrave;','&Eacute;','&Ecirc;','&Euml;','&Igrave;','&Iacute;','&Icirc;','&Iuml;','&ETH;','&Ntilde;','&Ograve;','&Oacute;','&Ocirc;','&Otilde;','&Ouml;','&times;','&Oslash;','&Ugrave;','&Uacute;','&Ucirc;','&Uuml;','&Yacute;','&THORN;','&szlig;','&agrave;','&aacute;','&acirc;','&atilde;','&auml;','&aring;','&aelig;','&ccedil;','&egrave;','&eacute;','&ecirc;','&euml;','&igrave;','&iacute;','&icirc;','&iuml;','&eth;','&ntilde;','&ograve;','&oacute;','&ocirc;','&otilde;','&ouml;','&divide;','&oslash;','&ugrave;','&uacute;','&ucirc;','&uuml;','&yacute;','&thorn;','&yuml;','&#256;','&#257;','&#258;','&#259;','&#260;','&#261;','&#262;','&#263;','&#264;','&#265;','&#266;','&#267;','&#268;','&#269;','&#270;','&#271;','&#272;','&#273;','&#274;','&#275;','&#276;','&#277;','&#278;','&#279;','&#280;','&#281;','&#282;','&#283;','&#284;','&#285;','&#286;','&#287;','&#288;','&#289;','&#290;','&#291;','&#292;','&#293;','&#294;','&#295;','&#296;','&#297;','&#298;','&#299;','&#300;','&#301;','&#302;','&#303;','&#304;','&#305;','&#306;','&#307;','&#308;','&#309;','&#310;','&#311;','&#312;','&#313;','&#314;','&#315;','&#316;','&#317;','&#318;','&#319;','&#320;','&#321;','&#322;','&#323;','&#324;','&#325;','&#326;','&#327;','&#328;','&#329;','&#330;','&#331;','&#332;','&#333;','&#334;','&#335;','&#336;','&#337;','&OElig;','&oelig;','&#340;','&#341;','&#342;','&#343;','&#344;','&#345;','&#346;','&#347;','&#348;','&#349;','&#350;','&#351;','&Scaron;','&scaron;','&#354;','&#355;','&#356;','&#357;','&#358;','&#359;','&#360;','&#361;','&#362;','&#363;','&#364;','&#365;','&#366;','&#367;','&#368;','&#369;','&#370;','&#371;','&#372;','&#373;','&#374;','&#375;','&Yuml;','&#377;','&#378;','&#379;','&#380;','&#381;','&#382;','&#383;','&#384;','&#385;','&#386;','&#387;','&#388;','&#389;','&#390;','&#391;','&#392;','&#393;','&#394;','&#395;','&#396;','&#397;','&#398;','&#399;','&#400;','&#401;','&fnof;','&#403;','&#404;','&#405;','&#406;','&#407;','&#408;','&#409;','&#410;','&#411;','&#412;','&#413;','&#414;','&#415;','&#416;','&#417;','&#418;','&#419;','&#420;','&#421;','&#422;','&#423;','&#424;','&#425;','&#426;','&#427;','&#428;','&#429;','&#430;','&#431;','&#432;','&#433;','&#434;','&#435;','&#436;','&#437;','&#438;','&#439;','&#440;','&#441;','&#442;','&#443;','&#444;','&#445;','&#446;','&#447;','&#448;','&#449;','&#450;','&#451;','&#452;','&#453;','&#454;','&#455;','&#456;','&#457;','&#458;','&#459;','&#460;','&#461;','&#462;','&#463;','&#464;','&#465;','&#466;','&#467;','&#468;','&#469;','&#470;','&#471;','&#472;','&#473;','&#474;','&#475;','&#476;','&#477;','&#478;','&#479;','&#480;','&#481;','&#482;','&#483;','&#484;','&#485;','&#486;','&#487;','&#488;','&#489;','&#490;','&#491;','&#492;','&#493;','&#494;','&#495;','&#496;','&#497;','&#498;','&#499;','&#500;','&#501;','&#502;','&#503;','&#504;','&#505;','&#506;','&#507;','&#508;','&#509;','&#510;','&#511;','&#512;','&#513;','&#514;','&#515;','&#516;','&#517;','&#518;','&#519;','&#520;','&#521;','&#522;','&#523;','&#524;','&#525;','&#526;','&#527;','&#528;','&#529;','&#530;','&#531;','&#532;','&#533;','&#534;','&#535;','&#536;','&#537;','&#538;','&#539;','&#540;','&#541;','&#542;','&#543;','&#544;','&#545;','&#546;','&#547;','&#548;','&#549;','&#550;','&#551;','&#552;','&#553;','&#554;','&#555;','&#556;','&#557;','&#558;','&#559;','&#560;','&#561;','&#562;','&#563;','&#564;','&#565;','&#566;','&#567;','&#568;','&#569;','&#570;','&#571;','&#572;','&#573;','&#574;','&#575;','&#576;','&#577;','&#578;','&#579;','&#580;','&#581;','&#582;','&#583;','&#584;','&#585;','&#586;','&#587;','&#588;','&#589;','&#590;','&#591;','&#647;','&#648;','&#649;','&#650;','&#651;','&#652;','&#653;','&#654;','&#655;','&#656;','&#657;','&#658;','&#659;','&#660;','&#661;','&#662;','&#663;','&#664;','&#665;','&#666;','&#667;','&#668;','&#669;','&#8220;','&#8221;','&#8217;'),
			$_);

		return $_;
	}


	protected function educateEllipses($_) {
	#
	#   Parameter:  String.
	#   Returns:    The string, with each instance of "..." translated to
	#               an ellipsis HTML entity. Also converts the case where
	#               there are spaces between the dots.
	#
	#   Example input:  Huh...?
	#   Example output: Huh&#8230;?
	#

		$_ = str_replace(array("...",     ". . .",), $this->ellipsis, $_);
		return $_;
	}


	protected function stupefyEntities($_) {
	#
	#   Parameter:  String.
	#   Returns:    The string, with each Kermit HTML entity translated to
	#               its ASCII counterpart.
	#
	#   Example input:  &#8220;Hello &#8212; world.&#8221;
	#   Example output: "Hello -- world."
	#

							#  en-dash    em-dash
		$_ = str_replace(array('&#8211;', '&#8212;'),
						 array('-',       '--'), $_);

		# single quote         open       close
		$_ = str_replace(array('&#8216;', '&#8217;'), "'", $_);

		# double quote         open       close
		$_ = str_replace(array('&#8220;', '&#8221;'), '"', $_);

		$_ = str_replace('&#8230;', '...', $_); # ellipsis

		return $_;
	}


	protected function processEscapes($_) {
	#
	#   Parameter:  String.
	#   Returns:    The string, with after processing the following backslash
	#               escape sequences. This is useful if you want to force a "dumb"
	#               quote or other character to appear.
	#
	#               Escape  Value
	#               ------  -----
	#               \\      &#92;
	#               \"      &#34;
	#               \'      &#39;
	#               \.      &#46;
	#               \-      &#45;
	#               \`      &#96;
	#
		$_ = str_replace(
			array('\\\\',  '\"',    "\'",    '\.',    '\-',    '\`'),
			array('&#92;', '&#34;', '&#39;', '&#46;', '&#45;', '&#96;'), $_);

		return $_;
	}


	protected function tokenizeHTML($str) {
	#
	#   Parameter:  String containing HTML markup.
	#   Returns:    An array of the tokens comprising the input
	#               string. Each token is either a tag (possibly with nested,
	#               tags contained therein, such as <a href="<MTFoo>">, or a
	#               run of text between tags. Each element of the array is a
	#               two-element array; the first is either 'tag' or 'text';
	#               the second is the actual value.
	#
	#
	#   Regular expression derived from the _tokenize() subroutine in 
	#   Brad Choate's MTRegex plugin.
	#   <http://www.bradchoate.com/past/mtregex.php>
	#
		$index = 0;
		$tokens = array();

		$match = '(?s:<!--.*?-->)|'.	# comment
				 '(?s:<\?.*?\?>)|'.				# processing instruction
												# regular tags
				 '(?:<[/!$]?[-a-zA-Z0-9:]+\b(?>[^"\'>]+|"[^"]*"|\'[^\']*\')*>)'; 

		$parts = preg_split("{($match)}", $str, -1, PREG_SPLIT_DELIM_CAPTURE);

		foreach ($parts as $part) {
			if (++$index % 2 && $part != '') 
				$tokens[] = array('text', $part);
			else
				$tokens[] = array('tag', $part);
		}
		return $tokens;
	}

}
