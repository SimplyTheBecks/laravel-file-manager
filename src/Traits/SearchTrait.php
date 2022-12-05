<?php


namespace Alexusmai\LaravelFileManager\Traits;


use Storage;

trait SearchTrait
{

    private $disc = '';
    private $storageItemsTypes = [
        'file' => ['getFunc' => 'allFiles', 'key' => 'files'],
        'dir'  => ['getFunc' => 'allDirectories', 'key' => 'directories']
    ];

    /**
     * Get content for search term
     *
     * @param       $disk
     * @param  null $path
     *
     * @return array
     */
    public function getSearchContent($disk, $term = null)
    {
        $this->disc = $disk;

        $content = [];
        $term = strtolower($term);
        foreach ($this->storageItemsTypes as $storageItemsType => $options) {
            $content[$options['key']] = collect(Storage::disk($disk)->{$options['getFunc']}())->filter(function ($path) use ($term){
                $path = strtolower($path);
                if (strpos($term, '/' ) !== false){
                    return strpos($path, $term) !== false;
                } else {
                    if(is_array($path = explode('/', $path))) {
                        return strpos(array_pop($path), $term) !== false;
                    } else {
                        return false;
                    }
                }
            })->map(function ($directory) use ($storageItemsType){
                return $this->formatListSearch($directory, $storageItemsType);
            })->values()->toArray();
        }

        return $content;
    }

    /**
     * @param        $path
     * @param string $type
     * @return array
     */
    private function formatListSearch($path, $type = 'dir')
    {
        $directory = explode('/', $path);

        $basename = array_pop($directory);

        $storageItem =  [
            "type" => $type,
            "path" => $path,
            "timestamp" => Storage::disk($this->disc)->lastModified($path),
            "dirname" => implode('/', $directory),
            "basename" => $basename,
        ];

        if($type == 'file'){
            $basenameArr = explode('.', $basename);
            $storageItem['extension'] = array_pop($basenameArr);
            $storageItem['filename'] = implode('', $basenameArr);
            $storageItem['visibility'] = Storage::disk($this->disc)->getVisibility($path);
            $storageItem['size'] = Storage::disk($this->disc)->size($path);
        }

        return $storageItem;
    }
}
