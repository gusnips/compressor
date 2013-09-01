CompressorClientScript
======================

Assets compressor for Yii Framework

--

Compressor for JS/CSS files using YUI compressor

Uses a modified version of https://github.com/gpbmike/PHP-YUI-Compressor for compression adapted to Yii

Uses a modified version of https://github.com/mrclay/minify/ to rewrite css urls adapted to Yii

# How to use

in your config define the class of clientScript component to use CompressorClientScript

```php
return array(
//...
'components'=>array(
	'class'=>'ext.CompressorClientScript.CompressorClientScript',//use whatever location you put the extension
	//the following options are optional and the values defined in this example are the default ones
	'compress'=>true,//wheter to compress the files
	//see YUICompressor class for more details
	'compressorOptions'=>array(
		//Insert a line breaks after '}' characters for css files or 
		//Insert a line break after the specified column number for js files
		'linebreak'=>false,
		//Display informational messages and warnings. (useful for cleaning up your JS)
		'verbose'=>false,
		//Minify only, no symbol obfuscation. Valid for js files
		'nomunge'=>false,
		//Preserve unnecessary semicolons (such as right before a '}'). Valid for js files
		'semi'=>false,
		//Disable all the built-in micro optimizations. Valid for js files
		'nooptimize'=>false,
		//path to the java binary
		//you can use, for example, 'C:\Program Files (x86)\Java\jre7\bin\java.exe' if you are windows
		'javaBin'=>'java',
	),
),
);
```

# How to clear the cache?

Just clean up protected/runtime/compressor folder and it will recreate the files

# How does it work?

It uses file_get_contents to get the files, so it can be either on your machine or load from external sources

It creates a compressed file in protected/runtime/compress for each page and then publishes it using assetsManager

For css files, it rewrites the css url's 
