<?php
/**
 * Rewrite file-relative URIs as root-relative in CSS files
 * 
 * based on CSS rewriter from https://github.com/mrclay/minify/
 * 
 * @author Gustavo Salomé Silva <gustavonips@gmail.com> modified to use Yii
 *  
 * @property string $currentDir The directory of the current CSS file. 
 * @property string $file The file path or URL of the current CSS file.
 * @property string $css The contents of the current CSS file. 
 * 
 */
class CssUriRewriter extends CComponent{
	
    /**
     * wheter to log debugging information here
     * @var string
     */
    public $debug = YII_DEBUG;
    
	/**
	 * @var string directory of this stylesheet
	 */
	private $_currentDir;
	
	/**
	 * @var string file path or url
	*/
	private $_file;
    
    /**
     * debug text to log
     * @var string
     */
    private $_debugText;
    /**
     * 
     * @var string $css
     */
    private $_css;
    
    /**
     * @param string $file the css file url or path
     */
    public function __construct($file)
    {
    	$this->_file=$file;
    }
    
    /**
     *
     * @return string
     */
    public function getCurrentDir()
    {
    	if($this->_currentDir===null)
    		$this->_currentDir = pathinfo($this->_file,PATHINFO_DIRNAME);
    	return $this->_currentDir;
    }
    
    /**
     * 
     * @return string
     */
    public function getCss()
    {
    	if($this->_css===null)
    		$this->_css=file_get_contents($this->_file);
    	return $this->_css;
    }
    
    /**
     * In CSS content, rewrite file relative URIs as root relative
     * 
     * @return string
     */
    public function rewrite() 
    {
        $this->_debugText.="currentDir : " . $this->getCurrentDir() . "\n";
        
        $css = $this->_trimUrls($this->getCss());
        
        // rewrite
        $css = preg_replace_callback('/@import\\s+([\'"])(.*?)[\'"]/',array($this, '_processUriCB'), $css);
        $css = preg_replace_callback('/url\\(\\s*([^\\)\\s]+)\\s*\\)/',array($this, '_processUriCB'), $css);
        
        if($this->debug)
        	Yii::log($this->_debugText);
        return $css;
    }
    
    /**
     * Get a root relative URI from a file relative URI
     *
     * <code>
     * CssUriRewriter::rewriteRelative(
     *       '../img/hello.gif'
     *     , '/home/user/www/css'  // path of CSS file
     *     , '/home/user/www'      // doc root
     * );
     * // returns '/img/hello.gif'
     * </code>
     * 
     * @param string $uri file relative URI
     * 
     * @param string $realCurrentDir realpath of the current file's directory.
     * 
     * @return string
     */
    protected function rewriteRelative($uri, $realCurrentDir)
    {
        // prepend path with current dir separator (OS-independent)
        $path = strtr($realCurrentDir, '/', DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR . strtr($uri, '/', DIRECTORY_SEPARATOR);
        
        $this->_debugText .= "file-relative URI  : {$uri}\n" . "path prepended     : {$path}\n";
        
        // fix to root-relative URI

        $uri = strtr($path, '/\\', '//');

        $uri = $this->removeDots($uri);
      
        $this->_debugText .= "traversals removed : {$uri}\n\n";
        
        return $uri;
    }

    /**
     * Remove instances of "./" and "../" where possible from a root-relative URI
     * @param string $uri
     * @return string
     */
    protected function removeDots($uri)
    {
        $uri = str_replace('/./', '/', $uri);
        // inspired by patch from Oleg Cherniy
        do {
            $uri = preg_replace('@/[^/]+/\\.\\./@', '/', $uri, 1, $changed);
        } while ($changed);
        return $uri;
    }

    /**
     * Get realpath with any trailing slash removed. If realpath() fails,
     * just remove the trailing slash.
     * 
     * @param string $path
     * 
     * @return mixed path with no trailing slash
     */
    protected function _realpath($path)
    {
        $realPath = realpath($path);
        if ($realPath !== false) {
            $path = $realPath;
        }
        return rtrim($path, '/\\');
    }
    
    /**
     * trim the urls
     * @param string $css
     * @return string
     */
    private function _trimUrls($css)
    {
        return preg_replace('/
            url\\(      # url(
            \\s*
            ([^\\)]+?)  # 1 = URI (assuming does not contain ")")
            \\s*
            \\)         # )
        /x', 'url($1)', $css);
    }
    
    /**
     * 
     * @param array $m
     * @return string
     */
    private function _processUriCB($m)
    {
        // $m matched either '/@import\\s+([\'"])(.*?)[\'"]/' or '/url\\(\\s*([^\\)\\s]+)\\s*\\)/'
        $isImport = ($m[0][0] === '@');
        // determine URI and the quote character (if any)
        if ($isImport) {
            $quoteChar = $m[1];
            $uri = $m[2];
        } else {
            // $m[1] is either quoted or not
            $quoteChar = ($m[1][0] === "'" || $m[1][0] === '"')
                ? $m[1][0]
                : '';
            $uri = ($quoteChar === '')
                ? $m[1]
                : substr($m[1], 1, strlen($m[1]) - 2);
        }
        // analyze URI
        if ('/' !== $uri[0]                  // root-relative
            && false === strpos($uri, '//')  // protocol (non-data)
            && 0 !== strpos($uri, 'data:')   // data protocol
        ) {
			// URI is file-relative: rewrite depending on options
			$uri = $this->rewriteRelative($uri, $this->getCurrentDir());
        }
        return $isImport ? "@import {$quoteChar}{$uri}{$quoteChar}":"url({$quoteChar}{$uri}{$quoteChar})";
    }
}
