<?php

namespace App\Entity;

use App\Repository\MediaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Media
{
    use LogTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $extension = null;

    private $file;

    private $tempFilename;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): self
    {
        $this->alt = $alt;

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(?string $extension): self
    {
        $this->extension = $extension;

        return $this;
    }

    public function setFile(File $file)
    {
        $this->file = $file;
        if (null !== $this->fileName) {
            $this->tempFilename = $this->fileName;

            $this->fileName = null;
            $this->alt = null;
            $this->extension = null;
        }
    }

    public function getFile()
    {
        return $this->file;
    }

    #[ORM\PrePersist()]
    #[ORM\PreUpdate()]
    public function preUpload()
    {
        if (null === $this->file) {
            return;
        }
        if ($this->file->guessExtension()) {
            $this->fileName = uniqid() . '.' . $this->file->guessExtension();
            $this->extension = $this->file->guessExtension();
        }

        $this->alt = $this->file->getClientOriginalName();
    }

    #[ORM\PostPersist()]
    #[ORM\PostUpdate()]
    public function upload()
    {
        if (null === $this->file) {
            return;
        }

        if (null !== $this->tempFilename) {
            $oldFile = $this->getUploadRootDir() . DIRECTORY_SEPARATOR . $this->tempFilename;
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }

        $this->file->move($this->getUploadRootDir(), $this->getFileName());
        $this->file = null;
    }

    #[ORM\PreRemove()]
    public function preRemoveUpload()
    {
        $this->tempFilename = $this->getUploadRootDir() . DIRECTORY_SEPARATOR . $this->fileName;
    }

    #[ORM\PostRemove()]
    public function removeUpload()
    {
        if (file_exists($this->tempFilename)) {
            unlink($this->tempFilename);
        }
    }

    public function getUploadDir()
    {
        return join(DIRECTORY_SEPARATOR, ['upload', 'media']);
    }

    protected function getUploadRootDir()
    {
        return join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'public', $this->getUploadDir()]);
    }

    public function getWebPath()
    {
        return $this->getUploadDir() . DIRECTORY_SEPARATOR . $this->getFileName();
    }

    /**
     * Get the value of fileName
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * Set the value of fileName
     *
     * @return  self
     */
    public function setFileName($fileName)
    {
        $this->fileName = $fileName;

        return $this;
    }
}
