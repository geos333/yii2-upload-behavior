<?php
/**
 * @author Alexey Samoylov <alexey.samoylov@gmail.com>
 * @link http://yiidreamteam.com/yii2/upload-behavior
 */

namespace yiidreamteam\upload;

use PHPThumb\GD;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\imagine\Image;
use Imagine\Image\Box;

/**
 * Class ImageUploadBehavior
 */
class ImageUploadBehavior extends FileUploadBehavior
{
    public $attribute = 'image';
    public $quality = 60;
    public $createThumbsOnSave = true;
    public $createThumbsOnRequest = false;

    /** @var array Thumbnail profiles, array of [width, height, ... PHPThumb options] */
    public $thumbs = [];

    /** @var string Path template for thumbnails. Please use the [[profile]] placeholder. */
    public $thumbPath = '@webroot/images/[[profile]]_[[pk]].[[extension]]';
    /** @var string Url template for thumbnails. */
    public $thumbUrl = '/images/[[profile]]_[[pk]].[[extension]]';

    public $filePath = '@webroot/images/[[pk]].[[extension]]';
    public $fileUrl = '/images/[[pk]].[[extension]]';

    /**
     * @inheritdoc
     */
    public function events()
    {
        return ArrayHelper::merge(parent::events(), [
            static::EVENT_AFTER_FILE_SAVE => 'afterFileSave',
        ]);
    }

    /**
     * @inheritdoc
     */
    public function cleanFiles()
    {
        parent::cleanFiles();
        foreach (array_keys($this->thumbs) as $profile) {
            @unlink($this->getThumbFilePath($this->attribute, $profile));
        }
    }

    /**
     * @param string $attribute
     * @param string $profile
     * @return string
     */
    public function getThumbFilePath($attribute, $profile = 'thumb', $extension = null)
    {
        $behavior = static::getInstance($this->owner, $attribute);
        $thumbPath = $behavior->thumbPath;

        if ($extension) {
            $thumbPath = str_replace('[[extension]]', $extension, $behavior->thumbPath);
        }

        return $behavior->resolveProfilePath($thumbPath, $profile);
    }

    /**
     * Resolves profile path for thumbnail profile.
     *
     * @param string $path
     * @param string $profile
     * @return string
     */
    public function resolveProfilePath($path, $profile)
    {
        $path = $this->resolvePath($path);
        return preg_replace_callback('|\[\[([\w\_/]+)\]\]|', function ($matches) use ($profile) {
            $name = $matches[1];
            switch ($name) {
                case 'profile':
                    return $profile;
            }
            return '[[' . $name . ']]';
        }, $path);
    }

    /**
     *
     * @param string $attribute
     * @param string|null $emptyUrl
     * @return string|null
     */
    public function getImageFileUrl($attribute, $extension = null, $emptyUrl = null)
    {
        if (!$this->owner->{$attribute}) {
            return $emptyUrl;
        }


        return $this->getUploadedFileUrl($attribute, $extension);
    }

    /**
     * @param string $attribute
     * @param string $profile
     * @param string|null $emptyUrl
     * @return string|null
     */
    public function getThumbFileUrl($attribute, $profile = 'thumb', $extension = null, $emptyUrl = null)
    {
        if (!$this->owner->{$attribute}) {
            return $emptyUrl;
        }

        $behavior = static::getInstance($this->owner, $attribute);

        $fileUrl = $behavior->thumbUrl;
        if ($behavior->createThumbsOnRequest) {
            $behavior->createThumbs($extension);
        }

        if ($extension) {
            $fileUrl = str_replace('[[extension]]', $extension, $behavior->thumbUrl);
        }
        return $behavior->resolveProfilePath($fileUrl, $profile);
    }

    /**
     * Creates image thumbnails
     */
    public function createThumbs($extension = null)
    {
        if (!class_exists(Image::class)) {
            throw new NotSupportedException("Yii2-imagine extension is required to use the UploadImageBehavior");
        }

        $path = $this->getUploadedFilePath($this->attribute);
        if (!is_file($path)) {
            return;
        }

        foreach ($this->thumbs as $profile => $config) {
            $thumbPath = static::getThumbFilePath($this->attribute, $profile);

            if ($extension) {
                $thumbPath = static::getThumbFilePath($this->attribute, $profile, $extension);
            }

            if ($thumbPath !== null) {
                if (!FileHelper::createDirectory(dirname($thumbPath))) {
                    throw new InvalidArgumentException(
                        "Directory specified in 'thumbPath' attribute doesn't exist or cannot be created."
                    );
                }
                if (!is_file($thumbPath)) {
                    $image = Image::getImagine()
                        ->open($path)
                        ->thumbnail(new Box($config['width'], $config['height']))
                        ->save($thumbPath, ['quality' => $this->quality]);
                }
            }
        }
    }

    /**
     * After file save event handler.
     */
    public function afterFileSave()
    {
        if ($this->createThumbsOnSave == true) {
            $this->createThumbs();
        }
    }
}
