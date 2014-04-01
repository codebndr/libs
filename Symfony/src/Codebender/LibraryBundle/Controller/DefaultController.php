<?php

namespace Codebender\LibraryBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;


class DefaultController extends Controller
{
	public function statusAction()
	{
		return new Response(json_encode(array("success" => true, "status" => "OK")));
	}

	public function testAction($auth_key)
	{
		if ($auth_key !== $this->container->getParameter('auth_key'))
		{
			return new Response(json_encode(array("success" => false, "message" => "Invalid authorization key.")));
		}

		set_time_limit(0); // make the script execution time unlimited (otherwise the request may time out)

		// change the current Symfony root dir
		chdir($this->get('kernel')->getRootDir()."/../");

		//TODO: replace this with a less horrible way to handle phpunit
		exec("phpunit -c app --stderr 2>&1", $output, $return_val);

		return new Response(json_encode(array("success" => (bool) !$return_val, "message" => implode("\n", $output))));
	}

	public function listAction($auth_key, $version)
    {
	    if ($auth_key !== $this->container->getParameter('auth_key'))
	    {
		    return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
	    }

	    if ($version == "v1")
	    {
		    $arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";

		    $finder = new Finder();
		    $finder2 = new Finder();
		    $finder3 = new Finder();

		    $finder->files()->name('*.ino')->name('*.pde');
		    $finder2->files()->name('*.ino')->name('*.pde');
		    $finder3->files()->name('*.ino')->name('*.pde');

		    $built_examples = array();
		    if (is_dir($arduino_library_files."examples"))
		    {
			    $finder->in($arduino_library_files."examples");
			    $built_examples = $this->iterateDir($finder, "v1");
		    }

		    $included_libraries = array();
		    if (is_dir($arduino_library_files."libraries"))
		    {
			    $finder2->in($arduino_library_files."libraries");
			    $included_libraries = $this->iterateDir($finder2, "v1");
		    }

		    $external_libraries = array();
		    if (is_dir($arduino_library_files."external-libraries"))
		    {
			    $finder3->in($arduino_library_files."external-libraries");
			    $external_libraries = $this->iterateDir($finder3, "v1");
		    }


		    ksort($built_examples);
		    ksort($included_libraries);
		    ksort($external_libraries);

		    return new Response(json_encode(array("success" => true,
			    "text" => "Successful Request!",
			    "categories" => array("Examples" => $built_examples,
				    "Builtin Libraries" => $included_libraries,
				    "External Libraries" => $external_libraries))));
	    }
	    else
	    {
		    return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid API version.")));
	    }
    }

	public function getExampleCodeAction($auth_key, $version, $library, $example)
	{
		if ($auth_key !== $this->container->getParameter('auth_key'))
		{
			return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
		}

		if ($version == "v1")
		{
			$arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";
			$files = array();

			$dir = $arduino_library_files."examples/";
			if (is_dir($dir))
			{
				$files = array_merge($files, $this->getExampleCodeFromDir($dir, $example));
			}

			$dir = $arduino_library_files."libraries/";
			if (is_dir($arduino_library_files."libraries/"))
			{
				$files = array_merge($files, $this->getExampleCodeFromDir($dir, $example));
			}

			$dir = $arduino_library_files."external-libraries/";
			if (is_dir($dir))
			{
				$files = array_merge($files, $this->getExampleCodeFromDir($dir, $example));
			}

			return new Response(json_encode(array('success' => true, "files" => $files)));
		}
		else
			{
			return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid API version.")));
		}
			}

    private function getExampleCodeFromDir($dir, $example)
    {
        $finder = new Finder();
        $finder->in($dir);
        $finder->name($example.".ino", $example.".pde");

        if(iterator_count($finder) == 0)
			{
            $example =  str_replace(":", "/", $example);
            $filename = pathinfo($example, PATHINFO_FILENAME);
            $finder->name($filename.".ino", $filename.".pde");
            if(iterator_count($finder) > 1)
            {
                $filesPath = NULL;
                foreach($finder as $e)
                {
                    $path = $e -> getPath();
                    if(!(strpos($path, $example)===false))
                    {
                        $filesPath = $e;
                        break;
                    }
                }
                if(!$filesPath)
                {
                    return array();
			}
		}
            else if(iterator_count($finder) == 0)
                return array();
		else
		{
                $filesPathIterator = iterator_to_array($finder, false);
                $filesPath = $filesPathIterator[0]->getPath();
            }
        }

        else
        {
            $filesPathIterator = iterator_to_array($finder, false);
            $filesPath = $filesPathIterator[0]->getPath();
        }
        $files = $this->getExampleFilesFromDir($filesPath);
        return $files;
    }

    private function getExampleFilesFromDir($dir)
    {
        $filesFinder = new Finder();
        $filesFinder->in($dir);
        $filesFinder->name('*.cpp')->name('*.h')->name('*.c')->name('*.S')->name('*.pde')->name('*.ino');

        $files = array();
        foreach($filesFinder as $file)
        {
            if($file->getExtension() == "pde")
                $name = $file->getBasename("pde")."ino";
            else
                $name = $file->getFilename();

            $files[]=array("filename" => $name, "code" => $file->getContents());

		}

        return $files;
	}

	public function getLibraryCodeAction($auth_key, $version)
	{
		if ($auth_key !== $this->container->getParameter('auth_key'))
		{
			return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
		}

		if ($version == "v1")
		{
			$arduino_library_files = $this->container->getParameter('arduino_library_directory')."/";

			$finder = new Finder();

			$request = $this->getRequest();

			// retrieve GET and POST variables respectively
			$library = $request->query->get('library');

			$filename = $library;
			$directory = "";

			$last_slash = strrpos($library, "/");
			if($last_slash !== false )
			{
				$filename = substr($library, $last_slash + 1);
				$vendor = substr($library, 0, $last_slash);
			}

			$response = $this->fetchLibraryFiles($finder, $arduino_library_files."/libraries/".$filename);
			if(empty($response))
				$response = $this->fetchLibraryFiles($finder, $arduino_library_files."/external-libraries/".$filename);

			if(empty($response))
				return new Response(json_encode(array("success" => false, "message" => "No Library named ".$library." found.")));

			return new Response(json_encode(array("success" => true, "message" => "Library found", "files" => $response)));

		}
		else
		{
			return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid API version.")));
		}
	}

	private function fetchLibraryFiles($finder, $directory)
	{
		if (is_dir($directory))
		{
			$finder->in($directory)->exclude('examples')->exclude('Examples');
			$finder->name('*.cpp')->name('*.h')->name('*.c')->name('*.S');

			$response = array();
			foreach ($finder as $file)
			{
				$response[] = array("filename" => $file->getRelativePathname(), "content" => $file->getContents());
			}
			return $response;
		}

	}

	private function iterateDir($finder, $version)
	{
		$libraries = array();

		foreach ($finder as $file)
		{
			$path = str_ireplace("/examples/", "/", $file->getRelativePath());
			$library_name = strtok($path, "/");
			$tmp = strtok("/");
			$type = "";
			$filename = $file->getBasename(".".$file->getExtension());
			while($tmp!= "" && !($tmp === false))
			{
				if($tmp != 'examples' && $tmp != 'Examples' && $tmp != $filename)
				{
					if($type == "")
						$type = $tmp;
					else
						$type = $type.":".$tmp;
				}
				$tmp = strtok("/");
			}
			$example_name= ($type == "" ?$filename : $type.":".$filename);

			if(!isset($libraries[$library_name]))
			{
				$libraries[$library_name] = array("description"=> "", "examples" => array());
			}
			$libraries[$library_name]["examples"][] = array("name" => $example_name);
		}
		return $libraries;
	}
}
