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

  /**
   * get a proper error message
   */ 
  public static function getError($msg,$output,$error) {
    $result=$msg;
	 	if ($output) $result.="<pre>".$output."</pre>";
    $result.="<pre style='color:red'>".$error."</pre>";  
    return $result;
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
    $pageContent = $page->getContent();
	  if (is_null($pageContent)) {
			$msg="[[Gram:$name]] failed";
      $output="";
      $error="The page does not exist!";
			$pyExecResult=self::getError($msg,$output,$error);
    } else { 
		  $content = $pageContent->getNativeData() . '';
    	self::writeStringToFile($pyCode,$content);
		  $python="python3";
		  $cmd=[$python,$pyCode];
		  foreach ($params as &$i) {
		  	 if ($i>0) $cmd[] =  $frame->expand( $i );
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
	  	if ($exitCode==0) {
        $pyExecResult=$output;
      } else {
		   	$cmdstring=implode(" ",$cmd);
			  $msg="[[Gram:$name]] $cmdstring failed exitCode=".$exitCode;
			  $pyExecResult=self::getError($msg,$output,$error);
		  }
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
