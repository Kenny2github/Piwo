<?php

use MediaWiki\Shell\Shell;
use MediaWiki\Logger\LoggerFactory;
define( 'CONTENT_MODEL_PIWO', 'Piwo' );
class Piwo {
	// Register render callbacks with the parser
	public static function onParserSetup( &$parser ) {
		//Create the function hook associating
		//the "python" magic word with execPy()
		$parser->setFunctionHook( 'piwo', 'Piwo::execPy', Parser::SFH_OBJECT_ARGS );
	}

  /**
   * write the given content to the given filePath
   */
  public static function writeStringToFile($filePath,$content) {
		$f = fopen($filePath, "w");
		fwrite( $f, $content );
		fclose( $f );
		unset($f);
  }

	// Render the output of {{#python:gram}}.
	public static function execPy( $parser, $frame, $params ) {
		//The inputs should contain a gram name and sys.argv
		//The output should also be wikitext.
		$page = WikiPage::factory( Title::newFromText( 'Gram:' . $frame->expand( $params[0] ) ) );
		$name = $frame->expand( $params[0] );
		$base="/tmp/";
	  $pyCode= $base . $name . ".py";
    $pyError= $base . $name . ".error";
		$content = $page->getContent()->getNativeData() . '';
    self::writeStringToFile($pyCode,$content);
		$python="python3";
		$cmd=[$python,$pyCode];
		foreach ($params as &$i) {
			 if ($i>0) $cmd[] = escapeshellarg( $frame->expand( $i ) );
		}
		unset ($i);
		# use shell framework for call
		# https://www.mediawiki.org/wiki/Manual:Shell_framework
    $result = Shell::command($cmd )
    	->environment( [ 'MEDIAWIKI' => 'to be used later' ] )
    	->limits( [ 'time' => 300 ] )
    	->execute();
    $exitCode = $result->getExitCode();
    $output = $result->getStdout();
    $error = $result->getStderr();
		#unlink($pyCode);
		#unlink($pyError);
		#$output="<source lang='python'>".$content."</source>";
	  if ($exitCode==0) {
      $pyExecResult=$output;
    } else {
			$cmdstring=implode(" ",$cmd);
			$pyExecResult="[[Gram:$name]] $cmdstring failed exitCode=".$exitCode;
	 		if ($output) $pyExecResult.="<pre>".$output."</pre>";
       $pyExecResult.="<pre style='color:red'>".$error."</pre>";  
		}
		return $pyExecResult; 
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
