<?php
/**
 * https://github.com/gpbmike/PHP-YUI-Compressor
 * 
 * @property array $files list of file to be compressed
 * @property string $jarPath absolute path to YUI jar file.
 * @property string $string content of files/strings to be compressed
 * 
 * @author Gustavo
 *
 */
class YUICompressor extends CComponent
{
	/**
	 * type of the files
	 * @var string
	 */
    public $type = 'js';
    
    /**
     * Insert a line breaks after '}' characters for css files
     * Insert a line break after the specified column number for js files
     * @var boolean
     */
	public $linebreak = false;
	
	/**
	 * Display informational messages and warnings. (useful for cleaning up your JS)
	 * @var boolean
	 */
	public $verbose = false;
	
	/**
	 * Minify only, no symbol obfuscation.
	 * valid for js files
	 * @var boolean
	 */
	public $nomunge = false;
	
	/**
	 * Preserve unnecessary semicolons (such as right before a '}').
	 * valid for js files
	 * @var boolean
	 */
	public $semi = false;
	
	/**
	 * Disable all the built-in micro optimizations.
	 * valid for js files
	 * @var boolean
	 */
	public $nooptimize=false;
	
	/**
	 * java command
	 * @var string
	 */
	public $javaBin='java';
    
    /**
     * path to the jar yuicompressor file
     * @var string
     */
    private $_jarPath;
    
    /**
     * list of file to be compressed
     * @var array
     */
    private $_files = array();
    
    /**
     * content of files/strings to be compressed
     * @var string
     */
    private $_string = '';
    
    /**
     * 
     * @param array $options
     */
    public function __construct($options=array())
    {
    	foreach($options as $option=>$value)
    		$this->$option=$value;
    }
    
    /**
     * add files (absolute path) to be compressed
     * @param array $files
     * @return YUICompressor
     */
    public function setFiles(array $files)
    {
    	foreach($files as $file)
    		$this->addFile($file);
    	return $this;
    }

    /**
     * add a file (absolute path) to be compressed
     * @param string $file
     * @return YUICompressor
     */
    function addFile($file)
    {
        array_push($this->_files,$file);
        return $this;
    }
    
    /**
     * alias for addString
     * @param string $value
     * @return YUICompressor
     */
    public function setString($value)
    {
    	return $this->addString($value);
    }
    
    /**
     * content of files/strings to be compressed
     * @return string
     */
    public function getString()
    {
    	return $this->_string;
    }
    
    /**
     * add a string to be compressed
     * @param string $string
     * @return YUICompressor
     */
    function addString($string)
    {
        $this->_string.=' '.$string;
        return $this;
    }
    
    /**
     * the meat and potatoes, executes the compression command in shell
     * @return string return compressed output
     */
    function compress()
    {
        
        // read the input
        foreach ($this->_files as $file) {
        	$string=file_get_contents($file);
        	if($string===false)
        		throw new Exception("Cannot read from uploaded file");
    		$this->_string.=$string;        
        }
    	
        // create single file from all input
        $input_hash = sha1($this->_string);
        $file = Yii::app()->getRuntimePath() . '/' . $input_hash . '.txt';
        $fh = fopen($file, 'w');
        if($fh===false)
        	throw new CException("Can't create new file");
        fwrite($fh, $this->_string);
        fclose($fh);
    	
    	// start with basic command
        $cmd = escapeshellarg($this->javaBin)." -Xmx128m -jar " . escapeshellarg($this->getJarPath()) . ' ' . escapeshellarg($file) . " --charset UTF-8";
    
        // set the file type
    	$cmd .= " --type " . (strtolower($this->type) == "css" ? "css" : "js");
    	
    	// and add options as needed
    	if ($this->linebreak && intval($this->linebreak) > 0)
            $cmd .= ' --line-break ' . intval($this->linebreak);

    	if ($this->verbose)
    	   $cmd .= " -v";
            
		if ($this->nomunge)
			$cmd .= ' --nomunge';
		
		if ($this->semi)
			$cmd .= ' --preserve-semi';
		
		if ($this->nooptimize)
			$cmd .= ' --disable-optimizations';
    
        // execute the command
    	exec($cmd . ' 2>&1', $raw_output,$status);
    	
    	// add line breaks to show errors in an intelligible manner
        $flattened_output = implode("\n", $raw_output);

        if($status===1)
        	throw new Exception('Failed to generate compressed file. Error: "'.$flattened_output.'"');
    	
    	// clean up (remove temp file)
    	unlink($file);
    	
    	// return compressed output
    	return $flattened_output;
    }
    
    /**
     * get the path yo yui compressor jar
     * @return string
     */
    public function getJarPath()
    {
    	if($this->_jarPath===null)
    		$this->_jarPath=__DIR__.DIRECTORY_SEPARATOR.'yuicompressor-2.4.8.jar';
    	return $this->_jarPath;
    }
    
    /**
     * set the path yo yui compressor jar
     * @param string $value
     * @return YUICompressor
     */
    public function setJarPath($value)
    {
    	$this->_jarPath=$value;
    	return $this;
    }
}

?>