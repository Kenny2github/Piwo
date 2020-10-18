<?php
define( 'CONTENT_MODEL_PIWO', 'Piwo' );

use MediaWiki\Shell\Shell;

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
		$filename = "/tmp/" . $name . ".py";
		$wd = __DIR__;
		$content = $page->getContent()->getNativeData() . '';
		$content = $content ?: <<<EOS
raise FileNotFoundError('No Gram named ' + mw.GRAM_NAME + ' exists or it is empty')
EOS;
		if (strpos($content, "from mw import") || strpos($content, "import mw")) {}
		else $content = <<<EOS
import mw
$content
EOS;
		$content = <<<EOS
import sys
sys.path.append('$wd')
$content
EOS;
		file_put_contents($filename, $content);
		$cmdargs = ["python3", $filename];
		foreach ($params as $par) {
			$cmdargs[] = $frame->expand( $par );
		}
		$result = Shell::command($cmdargs)
			->environment( [
				'MW_ROOT' => dirname(dirname(__DIR__)),
				'MW_GRAM_NAME' => $name
			] )
			->limits( [ 'time' => 300 ] )
			->execute();
		$exitCode = $result->getExitCode();
		$stdout = $result->getStdout();
		$stderr = $result->getStderr();
		if ($exitCode == 0) {
			$output = $stdout;
		} else {
			$args = implode
			$output = <<<EOS
<p class="error">[[Gram:$name]] exited with code $exitCode:</p>
<pre>$stdout</pre>
<pre class="error">$stderr</pre>
EOS;
		}
		return [ $output, 'noparse' => false ];
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
