<?php

namespace Alexusmai\LaravelFileManager\Traits;

use Alexusmai\LaravelFileManager\Services\ACLService\ACL;
use Illuminate\Support\Arr;
use Storage;

trait ContentTrait
{

    /**
     * Get content for the selected disk and path
     *
     * @param       $disk
     * @param  null $path
     *
     * @return array
     */
    public function getContent($disk, $path = null)
    {
        $content = Storage::disk($disk)->listContents($path);

        // get a list of directories
        $directories = $this->filterDir($disk, $content);

        // get a list of files
        $files = $this->filterFile($disk, $content);

        return compact('directories', 'files');
    }

    /**
     * Get directories with properties
     *
     * @param       $disk
     * @param  null $path
     *
     * @return array
     */
    public function directoriesWithProperties($disk, $path = null)
    {
        $content = Storage::disk($disk)->listContents($path);

        return $this->filterDir($disk, $content);
    }

    /**
     * Get files with properties
     *
     * @param       $disk
     * @param  null $path
     *
     * @return array
     */
    public function filesWithProperties($disk, $path = null)
    {
        $content = Storage::disk($disk)->listContents($path);

        return $this->filterFile($disk, $content);
    }

    /**
     * Get directories for tree module
     *
     * @param $disk
     * @param $path
     *
     * @return array
     */
    public function getDirectoriesTree($disk, $path = null)
    {
        $directories = $this->directoriesWithProperties($disk, $path);

        foreach ($directories as $index => $dir) {
            $directories[$index]['props'] = [
                'hasSubdirectories' => Storage::disk($disk)
                    ->directories($dir['path']) ? true : false,
            ];
        }

        return $directories;
    }

    /**
     * File properties
     *
     * @param       $disk
     * @param  null $path
     *
     * @return mixed
     */
    public function fileProperties($disk, $path = null)
    {
        $pathInfo = pathinfo($path);

        $properties = [
            'type'       => 'file',
            'path'       => $path,
            'basename'   => $pathInfo['basename'],
            'dirname'    => $pathInfo['dirname'] === '.' ? '' : $pathInfo['dirname'],
            'extension'  => $pathInfo['extension'] ?? '',
            'filename'   => $pathInfo['filename'],
            'size'       => Storage::disk($disk)->size($path),
            'timestamp'  => Storage::disk($disk)->lastModified($path),
            'visibility' => Storage::disk($disk)->getVisibility($path),
        ];

        // if ACL ON
        if ($this->configRepository->getAcl()) {
            return $this->aclFilter($disk, [$properties])[0];
        }

        return $properties;
    }

    /**
     * Get properties for the selected directory
     *
     * @param       $disk
     * @param  null $path
     *
     * @return array|false
     */
    public function directoryProperties($disk, $path = null)
    {
        $directory = Storage::disk($disk)->getMetadata($path);

        $pathInfo = pathinfo($path);

        /**
         * S3 didn't return metadata for directories
         */
        if (!$directory) {
            $directory['path'] = $path;
            $directory['type'] = 'dir';
        }

        $directory['basename'] = $pathInfo['basename'];
        $directory['dirname'] = $pathInfo['dirname'] === '.' ? ''
            : $pathInfo['dirname'];

        // if ACL ON
        if ($this->configRepository->getAcl()) {
            return $this->aclFilter($disk, [$directory])[0];
        }

        return $directory;
    }

    /**
     * Get only directories
     *
     * @param $content
     *
     * @return array
     */
    protected function filterDir($disk, $content)
    {
        // select only dir
        $dirsList = Arr::where($content, function ($item) {
            return $item['type'] === 'dir';
        });

        // remove 'filename' param
        $dirs = array_map(function ($item) {
            return Arr::except($item, ['filename']);
        }, $dirsList);

        // if ACL ON
        if ($this->configRepository->getAcl()) {
            return array_values($this->aclFilter($disk, $dirs));
        }

        return array_values($dirs);
    }

    /**
     * Get only files
     *
     * @param $disk
     * @param $content
     *
     * @return array
     */
    protected function filterFile($disk, $content)
    {
        // select only files
        $filesList = Arr::where($content, function ($item) {
            return $item['type'] === 'file';
        });

        $files = array_map(function ($item) use ($disk) {
            $pathInfo = pathinfo($item['path']);

            return [
                'type'       => $item['type'],
                'path'       => $item['path'],
                'basename'   => $pathInfo['basename'],
                'dirname'    => $pathInfo['dirname'] === '.' ? '' : $pathInfo['dirname'],
                'extension'  => $pathInfo['extension'] ?? '',
                'filename'   => $pathInfo['filename'],
                'size'       => $item['size'],
                'timestamp'  => Storage::disk($disk)->lastModified($item['path']),
                'visibility' => Storage::disk($disk)->getVisibility($item['path']),
            ];
        }, $filesList);

        // if ACL ON
        if ($this->configRepository->getAcl()) {
            return array_values($this->aclFilter($disk, $files));
        }

        return array_values($files);
    }

    /**
     * ACL filter
     *
     * @param $disk
     * @param $content
     *
     * @return mixed
     */
    protected function aclFilter($disk, $content)
    {
        $acl = resolve(ACL::class);

        $withAccess = array_map(function ($item) use ($acl, $disk) {
            // add acl access level
            $item['acl'] = $acl->getAccessLevel($disk, $item['path']);

            return $item;
        }, $content);

        // filter files and folders
        if ($this->configRepository->getAclHideFromFM()) {
            return array_filter($withAccess, function ($item) {
                return $item['acl'] !== 0;
            });
        }

        return $withAccess;
    }
}
