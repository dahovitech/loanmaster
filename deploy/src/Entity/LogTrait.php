<?php 

namespace App\Entity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

trait LogTrait{

     /**
     * @var \DateTime|null
     */
    #[ORM\Column(name: 'created_at', type: "datetime", nullable:true)]
    #[Gedmo\Timestampable(on: 'create')]
    private $createdAt;

    /**
     * @var \DateTime|null
     */
     #[ORM\Column(name:'updated_at', type: "datetime", nullable:true)]
     #[Gedmo\Timestampable(on: 'update')]
    private $updatedAt;

    #[ORM\Column(name:'payment_at', type: "datetime", nullable:true)]
    #[Gedmo\Timestampable(on: 'create')]
    private  $paymentAt = null;

    /**
     * Get the value of createdAt
     */ 
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set the value of createdAt
     *
     * @return  self
     */ 
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get the value of updatedAt
     */ 
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set the value of updatedAt
     *
     * @return  self
     */ 
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}