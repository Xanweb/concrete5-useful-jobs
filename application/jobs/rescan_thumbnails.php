<?php 
namespace Application\Job;

use QueueableJob;
use Concrete\Core\File\Image\Thumbnail\Type\Type as ThumbnailType;
use Concrete\Core\Entity\File\File as FileEntity;
use Concrete\Core\File\Type\Type as FileType;
use ZendQueue\Queue as ZendQueue;
use ZendQueue\Message as ZendQueueMessage;
use Exception;
use File;
use FileList;

class RescanThumbnails extends QueueableJob
{
    public $jSupportsQueue = true;

    protected $thumbTypes;

    public function __construct()
    {
        parent::__construct();
        // This causes core upgrade issues due to schema changes
        try {
            $this->thumbTypes = ThumbnailType::getVersionList();
        } catch (Exception $e) {
            $this->thumbTypes = [];
        }
    }

    public function getJobName()
    {
        return t('Rescan Thumbnails (Update Only)');
    }

    public function getJobDescription()
    {
        return t('Generate all missing thumbnails.');
    }

    public function start(ZendQueue $q)
    {
        $list = new FileList();
        $list->filterByType(FileType::T_IMAGE);
        $list->ignorePermissions();
        $files = $list->executeGetResults();
        foreach ($files as $f) {
            $q->send($f['fID']);
        }
    }

    public function processQueueItem(ZendQueueMessage $msg)
    {
        try {
            $f = File::getByID($msg->body);
            if (is_object($f)) {
                $this->generateMissingFileThumbs($f);
            } else {
                throw new Exception(t('Error occurred while getting the file object of fID: %s', $msg->body));
            }
        } catch (Exception $e) {
            return false;
        }
    }

    protected function generateMissingFileThumbs(FileEntity $f)
    {
        $fv = $f->getApprovedVersion();

        $imagewidth = $fv->getAttribute('width');
        $imageheight = $fv->getAttribute('height');

        try {
            $image = $fv->getImagineImage();

            if ($image) {
                /* @var \Imagine\Imagick\Image $image */
                if (!$imagewidth) {
                    $imagewidth = $image->getSize()->getWidth();
                }
                if (!$imageheight) {
                    $imageheight = $image->getSize()->getHeight();
                }
                $fsl = $f->getFileStorageLocationObject()->getFileSystemObject();

                foreach ($this->thumbTypes as $type) {
                    // check the thumbnail if it exists
                    $path = $type->getFilePath($fv);
                    if ($fsl->has($path)) {
                        continue;
                    }

                    // if image is smaller than size requested, don't create thumbnail
                    if ($imagewidth < $type->getWidth() && $imageheight < $type->getHeight()) {
                        continue;
                    }

                    // This should not happen as it is not allowed when creating thumbnail types and both width and heght
                    // are required for Exact sizing but it's here just in case
                    if (ThumbnailType::RESIZE_EXACT === $type->getSizingMode() && (!$type->getWidth() || !$type->getHeight())) {
                        continue;
                    }

                    // If requesting an exact size and any of the dimensions requested is larger than the image's
                    // don't process as we won't get an exact size
                    if (ThumbnailType::RESIZE_EXACT === $type->getSizingMode() && ($imagewidth < $type->getWidth() || $imageheight < $type->getHeight())) {
                        continue;
                    }

                    // if image is the same width as thumbnail, and there's no thumbnail height set,
                    // or if a thumbnail height set and the image has a smaller or equal height, don't create thumbnail
                    if ($imagewidth == $type->getWidth() && (!$type->getHeight() || $imageheight <= $type->getHeight())) {
                        continue;
                    }

                    // if image is the same height as thumbnail, and there's no thumbnail width set,
                    // or if a thumbnail width set and the image has a smaller or equal width, don't create thumbnail
                    if ($imageheight == $type->getHeight() && (!$type->getWidth() || $imagewidth <= $type->getWidth())) {
                        continue;
                    }

                    // otherwise file is bigger than thumbnail in some way, proceed to create thumbnail
                    $fv->generateThumbnail($type);
                }
            }
            unset($image);
            $fv->releaseImagineImage();
        } catch (\Imagine\Exception\InvalidArgumentException $e) {
            unset($image);
            $fv->releaseImagineImage();

            return false;
        } catch (\Imagine\Exception\RuntimeException $e) {
            unset($image);
            $fv->releaseImagineImage();

            return false;
        }
    }

    public function finish(ZendQueue $q)
    {
        return t('All files have been processed.');
    }
}
