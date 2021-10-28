Kermit
===============

Kermit 1.0.0 - 27 Oct 2021

by Adriano Santi
<https://www.adrianosanti.com>

90% of the work has been copied from previous
work done by Michel Fortin (<https://michelf.ca/>)
and John Gruber (<https://daringfireball.net/>), 
but since their licence does not allow modifications
that mention the original project name, you’re going
to have to Google it.

Introduction
------------

This is a parser that includes the aforementioned lib done 
by Michel Fortin, which was a PHP port of the original
Perl lib by John Gruber.

Kermit is a free web typography aid for web writers, content
managers and site admins, which translates plain ASCII
punctuation characters into their typographically correct
HTML entity equivalents.

My addition to it, since I work on mostly non-English websites,
has been to add an option to also convert latin-extended characters
(accented characters, diacritics and the like) to their equivalent
HTML entities.

Kermit has the capabilities to:

*	Convert straight quotes (`"` and `'`) into the typographically-
correct “smart quotes”.
*	Convert backtick-style quotes (` ``such as this`` `) into
typographically-correct “smart quotes”.
*	Convert faux dashes (`--` and `---`) into en- and em-dashes.
*	Convert three consecutive dots (`...`) into an ellipsis entity.

This allows you (or your users) to write, edit and save using plain
old ASCII straight quotes and faux-dashes, or paste in text with
plain accented characters and non-HTML smart quotes. It will all be
converted to typographically-correct and HTML-friendly content at
the output stage.

Kermit will not change characters within `<pre>`, `<code>`, `<kbd>`
or `<script>` tag blocks, since those are normally used to display
text where typographically-correct punctuation and quotes would
be a hindrance since they’re normally used to display source code
or example markup.


### Backslash Escapes ###

If you want to keep Kermit from converting your quotes, you can use
the following backslash escape sequences to make Kermit keep them
as they are. This is done by converting the character to its
respective HTML numeric entity.

    Escape  Value  Character
    ------  -----  ---------
      \\    &#92;    \
      \"    &#34;    "
      \'    &#39;    '
      \.    &#46;    .
      \-    &#45;    -
      \`    &#96;    `


This is useful, for example, when you want to use straight quotes as
foot and inch marks:

    6\'2\" tall

translates into:

    6&#39;2&#34; tall

The HTML output, after being interpreted by the browser would look
like this:

    6'2" tall


Requirements
------------

Kermit requires **PHP 5.3 or later**.


Usage
-----

Kermit can be used with class autoloading. For autoloading to work,
your project needs to have a PSR-0-compatible autoloader.
For an example of a minimal autoloader setup, see [Michelf’s example](https://github.com/michelf/php-smartypants/blob/lib/Readme.php>).

With class autoloading in place, putting the ‘Kermit’ folder in your 
include path should be enough for this to work:

	use Ribbit\Kermit;
	$html_output = Kermit::defaultTransform($html_input);

If you are using Kermit with another text-processing function that
generates HTML (such as Markdown or Wordpress), you should filter
the text *after* the HTML has been generated. Here’s an example
using PHP Markdown by Michel Fortin:

	use Michelf\Markdown, Ribbit\Kermit;
	$my_html = Markdown::defaultTransform($my_text);
	$my_html = Kermit::defaultTransform($my_html);

### Usage Without an Autoloader ###

To use Kermit without an autoloader, you can still use **include** or
**require** to access the parser. Here’s such an example:

	require_once 'includes/Kermit.php';


Shortcomings and Edge Cases
---------------------------

Sometimes, quotes will be curled the wrong way, especially when they
are used at the beginning of sentences, for example:

    'Twas the night before Christmas.

In the case above, Kermit would turn the single quote mark into an
opening single-quote, when in fact it should be a closing one. But
this is an edge case which even full-on word processors get wrong.
In such cases, it’s best to just do it manually (using &rsquo;), in 
which case Kermit will just ignore it and move on.


Credit where Credit is Due
--------------------------

Kermit is heavily based on the work of [John Gruber](https://daringfireball.net/) and [Michel Fortin](https://michelf.ca/).

It wasn’t until I began to work on mostly non-English language
projects that it occurred to me to change their work. Unfortunately
the licence they use does not allow me to mention the name of
such work, but if you can use a thesaurus, look up “clever trousers”.