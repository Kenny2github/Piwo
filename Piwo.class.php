<?php
define( 'CONTENT_MODEL_PIWO', 'Piwo' );
class Piwo {
	// Register render callbacks with the parser
	public static function onParserSetup( &$parser ) {
		global $wgSyntaxHighlightModels;
		//Create the function hook associating
		//the "python" magic word with execPy()
		$parser->setFunctionHook( 'piwo', 'Piwo::execPy', Parser::SFH_OBJECT_ARGS );
		// Register the python syntax highlighter for Gram pages if SH is registered
		if (isset($wgSyntaxHighlightModels)) {
			$wgSyntaxHighlightModels[CONTENT_MODEL_PIWO] = 'python3';
		}
	}
	// Render the output of {{#python:gram}}.
	public static function execPy( $parser, $frame, $params ) {
		//The inputs should contain a gram name and sys.argv
		//The output should also be wikitext.
		$page = WikiPage::factory( Title::newFromText( 'Gram:' . $frame->expand( $params[0] ) ) );
		$name = $frame->expand( $params[0] );
		$content = $page->getContent()->getNativeData() . '';
		$content = "import sys\nsys.path.append('" . getcwd() . "/extensions/Piwo')" . (((strpos($content, "from mw import") or strpos($content, "import mw")) === false) ? "\nimport mw" : "") . "\nsys.stderr = open('/tmp/" . $name . ".error', 'w')\ndel sys\n" . $content . "\nimport sys\nsys.stderr.close()\ndel sys";
		$f = fopen("/tmp/" . $name . ".py", "w");
		fwrite( $f, $content );
		fclose( $f );
		unset($f);
		foreach ($params as &$i) {
			$i = escapeshellarg( $frame->expand( $i ) );
		}
		unset($i);
		$output = shell_exec("python3 /tmp/" . $name . '.py ' . implode(' ', array_slice($params, 1)));
		if ($output === null) { //$output = file_get_contents("/var/tmp/test");
			$output = '<pre class="error">' . htmlspecialchars( file_get_contents("/tmp/" . $name . ".error") ) . '</pre>';
		}
		unlink("/tmp/" . $name . ".py");
		unlink("/tmp/" . $name . ".error");
		return $output;
	}

	public static function contentHandlerDefaultModelFor( Title $title, &$model ) {
		if ( $title->getNamespace() == NS_GRAM ) {
			$model = CONTENT_MODEL_PIWO;
			return false;
		}
		return true;
	}
}

class PiwoContent extends TextContent {
	function __construct( $text ){
		parent::__construct( $text, CONTENT_MODEL_PIWO );
	}

	protected function fillParserOutput(
		Title $title, $revId, ParserOptions $options, $generateHtml, ParserOutput &$output
	) {
		$text = $this->getNativeData();
		$output->setText( self::getPOText( $output ) .
			"<p>" . wfMessage( 'piwo-purge-reminder' )->text() . "</p>\n" .
			"<pre class='mw-code mw-script' dir='ltr'>\n" .
			htmlspecialchars( $text ) .
			"\n</pre>\n"
		);
		return $output;
	}

	private static function getPOText( ParserOutput $po ) {
		return is_callable( [ $po, 'getRawText' ] )
			? $po->getRawText()
			: $po->getText();
	}
}

class PiwoContentHandler extends CodeContentHandler {
	public function __construct(
		$modelId = CONTENT_MODEL_PIWO, $formats = [ CONTENT_FORMAT_TEXT ]
	) {
		parent::__construct( $modelId, $formats );
	}

	protected function getContentClass() {
		return 'PiwoContent';
	}

	public function canBeUsedOn( Title $title ) {
		if ( $title->getNamespace() !== NS_GRAM ) {
			return false;
		}

		return parent::canBeUsedOn( $title );
	}
}
