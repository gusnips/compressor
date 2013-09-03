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

		if(!empty($this->scriptMap))
			$this->remapScripts();

		$this->unifyScripts();
		
		if($this->compress)
		{
			if($this->enableJavaScript && count($this->scriptFiles))
			{
				$key=$this->_getScriptFilesCompressKey();
				if(($path=Yii::app()->getGlobalState($key))===null || !file_exists($path))
				{
					$path=$this->compressScriptFiles("{$key}.js");
					//$this->
					Yii::app()->setGlobalState($key, $path);
				}
				$url=Yii::app()->assetManager->publish($path);
				$this->scriptFiles=array(
					$this->coreScriptPosition=>array($url=>$url)
				);
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
	protected function compressScriptFiles($filename)
	{
		$jsFiles=$this->_getJsFiles();
		
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
	private function _getScriptFilesCompressKey()
	{
		$files=$this->_getJsFiles();
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
	private function _getJsFiles()
	{
		if($this->_jsFiles===null)
		{
			$jsFiles=array();
			if(isset($this->scriptFiles[self::POS_HEAD]))
			{
				foreach($this->scriptFiles[self::POS_HEAD] as $file=>$value)
				{
					if(substr($file,0,1)==='/')//it's a relative internal url, prefix with root path
						$file=$_SERVER['DOCUMENT_ROOT'].$file;
					$jsFiles[]=$file;
				}
			}
			if(isset($this->scriptFiles[self::POS_BEGIN]))
			{
				foreach($this->scriptFiles[self::POS_BEGIN] as $file=>$value)
				{
					if(substr($file,0,1)==='/')//it's a relative internal url, prefix with root path
						$file=$_SERVER['DOCUMENT_ROOT'].$file;
					$jsFiles[]=$file;
				}
			}
			if(isset($this->scriptFiles[self::POS_END]))
			{
				foreach($this->scriptFiles[self::POS_END] as $file=>$value)
				{
					if(substr($file,0,1)==='/')//it's a relative internal url, prefix with root path
						$file=$_SERVER['DOCUMENT_ROOT'].$file;
					$jsFiles[]=$file;
				}
			}
			$this->_jsFiles=$jsFiles;
		}
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