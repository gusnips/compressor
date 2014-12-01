<?php
require_once __DIR__.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'YUICompressor.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'CssUriRewriter.php';
/**
 * 
 * @author Gustavo
 *
 */
class CompressorClientScript extends CClientScript
{
	/**
	 * wheter to minify & compress files
	 * @var boolean
	 */
	public $compress=true;
	
	/**
	 * options for YUICompressor class 
	 * @var array
	 * @see YUICompressor
	 */
	public $compressorOptions=array();
	private $_jsFiles;
	private $_cssFiles;
	private $_tmpPath;
	
	/**
	 * (non-PHPdoc)
	 * @see CClientScript::render()
	 */
	public function render(&$output)
	{
		if(!$this->hasScripts)
			return;

		$this->renderCoreScripts();
		
		if($this->compress)
		{
			if($this->enableJavaScript && count($this->scriptFiles))
			{
                //Get the positions from the array keys
                $positions=$this->scriptFiles;
                $files = array();
                foreach($positions as $position => $scripts)
                {
                    $key=$this->_getScriptFilesCompressKey($position);

                    if(($path=Yii::app()->getGlobalState($key))===null || !file_exists($path))
                    {
                        $path=$this->compressScriptFiles("{$key}.js", $position);
                        Yii::app()->setGlobalState($key, $path);
                    }
                    $url=Yii::app()->assetManager->publish($path);
                    $files[$position]=array($url => $url);
                }

                $this->scriptFiles = $files;
			}
			if(count($this->cssFiles))
			{
				$medias=array_keys($this->_getCssFiles());
				$files=array();
				foreach($medias as $media)
				{
					$key=$this->_getCssFilesCompressKey($media);
					if(($path=Yii::app()->getGlobalState($key))===null|| !file_exists($path))
					{
						$path=$this->compressCssFiles($media,"{$key}.css");
						//$this->
						Yii::app()->setGlobalState($key, $path);
					}
					$url=Yii::app()->assetManager->publish($path);
					$files[$url]=$media;
				}
				$this->cssFiles=$files;
			}
		}

        if(!empty($this->scriptMap))
            $this->remapScripts();

        $this->unifyScripts();

		$this->renderHead($output);
		if($this->enableJavaScript)
        {
			$this->renderBodyBegin($output);
			$this->renderBodyEnd($output);
		}
	}
	
	/**
	 * compress and minify all css files by media
	 * @return array|boolean array(media=>contents) 
	 * false if no files
	 */
	protected function compressCssFiles($media,$filename)
	{
		$cssFiles=$this->_getCssFiles($media);
		
		$string='';
		
		foreach($cssFiles as $cssFile)
		{
			$urlRewriter=new CssUriRewriter($cssFile);
			$string.=$urlRewriter->rewrite();
		}
		//compress
		$compressor=new YUICompressor($this->compressorOptions);
		$compressor->type='css';
		$compressor->addString($string);
		$content=$compressor->compress();
		
		$filePath=$this->getCompressorTempPath().DIRECTORY_SEPARATOR.$filename;
		if(!file_exists(dirname($filePath)))
			mkdir(dirname($filePath));
		if(file_put_contents($filePath, $content)===false)
			throw new Exception('Failed to save compressed file');
		return $filePath;
	}
	
	/**
	 * compress and minify all js files 
	 * @return string|boolean contents of js file
	 * false if no files
	 */
	protected function compressScriptFiles($filename, $position)
	{
		$jsFiles=$this->_getJsFiles($position);
		
		$compressor=new YUICompressor($this->compressorOptions);
		$compressor->type='js';
		$compressor->setFiles($jsFiles);
		$content=$compressor->compress();
		
		$filePath=$this->getCompressorTempPath().DIRECTORY_SEPARATOR.$filename;
		if(file_put_contents($filePath, $content)===false)
			throw new Exception('Failed to save compressed file');
		return $filePath;
	}
	/**
	 * 
	 * @return string
	 */
	private function _getScriptFilesCompressKey($position)
	{
		$files=$this->_getJsFiles($position);
		return md5(implode('',$files));
	}
	/**
	 * 
	 * @param string $media
	 * @return string
	 */
	private function _getCssFilesCompressKey($media)
	{
		$files=$this->_getCssFiles($media);
		return md5($media.implode('',$files));
	}
	
	/**
	 * @return array
	 */
	private function _getJsFiles($position = null)
	{
		if($this->_jsFiles===null)
		{
			$jsFiles=array();
			if(isset($this->scriptFiles[self::POS_HEAD]))
			{
                if(count($this->scriptFiles[self::POS_HEAD]))
                    $jsFiles[self::POS_HEAD] = array();

				foreach($this->scriptFiles[self::POS_HEAD] as $file=>$value)
				{
					if(substr($file,0,1)==='/')//it's a relative internal url, prefix with root path
						$file=$_SERVER['DOCUMENT_ROOT'].$file;
                    array_push($jsFiles[self::POS_HEAD], $file);
				}
			}
			if(isset($this->scriptFiles[self::POS_BEGIN]))
			{
                if(count($this->scriptFiles[self::POS_BEGIN]))
                    $jsFiles[self::POS_BEGIN] = array();

				foreach($this->scriptFiles[self::POS_BEGIN] as $file=>$value)
				{
					if(substr($file,0,1)==='/')//it's a relative internal url, prefix with root path
						$file=$_SERVER['DOCUMENT_ROOT'].$file;
					array_push($jsFiles[self::POS_BEGIN], $file);
				}
			}
			if(isset($this->scriptFiles[self::POS_END]))
			{
                if(count($this->scriptFiles[self::POS_END]))
                    $jsFiles[self::POS_END] = array();

				foreach($this->scriptFiles[self::POS_END] as $file=>$value)
				{
					if(substr($file,0,1)==='/')//it's a relative internal url, prefix with root path
						$file=$_SERVER['DOCUMENT_ROOT'].$file;
                    array_push($jsFiles[self::POS_END], $file);
				}
			}
			$this->_jsFiles=$jsFiles;
		}

        if($position !== null)
            return $this->_jsFiles[$position];
		return $this->_jsFiles;
	}
	
	/**
	 * list of css files
	 * @return array
	 */
	private function _getCssFiles($media=null)
	{
		if($this->_cssFiles===null)
		{
			$cssFiles=array();
			foreach($this->cssFiles as $file=>$fileMedia)
			{
				if($fileMedia==='')
					$fileMedia='screen';
				if(!isset($cssFiles[$fileMedia]))
					$cssFiles[$fileMedia]=array();
				if(substr($file,0,1)==='/')//it's a relative internal url, prefix with root path
					$file=$_SERVER['DOCUMENT_ROOT'].$file;
				$cssFiles[$fileMedia][]=$file;
			}
			$this->_cssFiles=$cssFiles;
		}
		if($media!==null)
			return $this->_cssFiles[$media];
		return $this->_cssFiles;
	}
	
	/**
	 * 
	 * @return string
	 */
	protected function getCompressorTempPath()
	{
		if($this->_tmpPath===null)
		{
			$this->_tmpPath=Yii::app()->getRuntimePath().DIRECTORY_SEPARATOR.'compressor';
			if(!file_exists($this->_tmpPath))
				mkdir($this->_tmpPath);
		}
		return $this->_tmpPath;
	}
}