<?php

namespace App\Entity;


use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use LogTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(type: "string", length: 190, nullable: true)]
    private $lastname;

    #[ORM\Column(type: "string", length: 190, nullable: true)]
    private $firstname;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $locale = null;

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(type: 'boolean')]
    private $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telephone = null;

    #[ORM\OneToOne(targetEntity: Media::class, cascade: ["persist", "remove"])]
    private $avatar;

    #[ORM\Column(type: "string", length: 190, nullable: true)]
    private $civility;

    #[ORM\Column(type: "datetime", nullable: true)]
    private $birthdate;

    #[ORM\Column(type: "string", length: 190, nullable: true)]
    private $nationality;

    #[ORM\Column(type: "string", length: 190, nullable: true)]
    private $address;

    #[ORM\Column(type: "string", length: 10, nullable: true)]
    private $zipcode;

    #[ORM\Column(type: "string", length: 190, nullable: true)]
    private $city;

    #[ORM\Column(type: "string", length: 190, nullable: true)]
    private $country;

    #[ORM\Column(type: "string", length: 190, nullable: true)]
    private $professionnalSituation;

    #[ORM\Column(type: "string", length: 190, nullable: true)]
    private $monthlyIncome;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class, cascade: ["persist", "remove"])]
    private Collection $notifications;

    #[ORM\Column(type: 'boolean')]
    private bool $isEnabled = true;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $confirmationToken = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\OneToOne(targetEntity: Media::class, cascade: ["persist", "remove"])]
    private $idDocument;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $idDocumentType = null;

    #[ORM\OneToOne(targetEntity: Media::class, cascade: ["persist", "remove"])]
    private $proofOfAddress;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $proofOfAddressType = null;

    #[ORM\OneToOne(targetEntity: Media::class, cascade: ["persist", "remove"])]
    private $integrityDocument;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $integrityDocumentType = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $verificationStatus = null;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice(choices: ['individual', 'professional'])]
    private string $accountType = 'individual';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $companyName;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $registrationNumber;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $companyAddress;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $companyEmail;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $companyTelephone;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $companyLegalStatus;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $companyCity;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $companyCountry;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $companyZipcode;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $companyProfessionalExperience;

    #[ORM\OneToOne(targetEntity: Media::class, cascade: ["persist", "remove"])]
    private $businessLicense;

    #[ORM\OneToOne(targetEntity: Media::class, cascade: ["persist", "remove"])]
    private $businessRegistration;

    #[ORM\OneToOne(targetEntity: Media::class, cascade: ["persist", "remove"])]
    private $taxCertificate;



    /**
     * @var Collection<int, Loan>
     */
    #[ORM\OneToMany(targetEntity: Loan::class, mappedBy: 'user', cascade: ["persist", "remove"])]
    private Collection $loans;

    public function getAccountType(): string
    {
        return $this->accountType;
    }

    public function setAccountType(string $accountType): self
    {
        $this->accountType = $accountType;
        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): self
    {
        $this->companyName = $companyName;
        return $this;
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(?string $registrationNumber): self
    {
        $this->registrationNumber = $registrationNumber;
        return $this;
    }

    public function getCompanyAddress(): ?string
    {
        return $this->companyAddress;
    }

    public function setCompanyAddress(?string $companyAddress): self
    {
        $this->companyAddress = $companyAddress;
        return $this;
    }

    public function getBusinessLicense(): ?Media
    {
        return $this->businessLicense;
    }

    public function setBusinessLicense(?Media $businessLicense): self
    {
        $this->businessLicense = $businessLicense;
        return $this;
    }

    public function getBusinessRegistration(): ?Media
    {
        return $this->businessRegistration;
    }

    public function setBusinessRegistration(?Media $businessRegistration): self
    {
        $this->businessRegistration = $businessRegistration;
        return $this;
    }

    public function getTaxCertificate(): ?Media
    {
        return $this->taxCertificate;
    }

    public function setTaxCertificate(?Media $taxCertificate): self
    {
        $this->taxCertificate = $taxCertificate;
        return $this;
    }


    public function getConfirmationToken(): ?string
    {
        return $this->confirmationToken;
    }

    public function setConfirmationToken(?string $confirmationToken): self
    {
        $this->confirmationToken = $confirmationToken;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function isIsEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function __construct()
    {
        $this->notifications = new ArrayCollection();
        $this->loans = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function hasRole($role)
    {
        return in_array(strtoupper($role), $this->getRoles(), true);
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function isIsVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): self
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }

    /**
     * Get the value of civility
     */
    public function getCivility()
    {
        return $this->civility;
    }

    /**
     * Set the value of civility
     *
     * @return  self
     */
    public function setCivility($civility)
    {
        $this->civility = $civility;

        return $this;
    }

    /**
     * Get the value of lastname
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * Set the value of lastname
     *
     * @return  self
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;

        return $this;
    }

    /**
     * Get the value of firstname
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * Set the value of firstname
     *
     * @return  self
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;

        return $this;
    }

    /**
     * Get the value of birthdate
     */
    public function getBirthdate()
    {
        return $this->birthdate;
    }

    /**
     * Set the value of birthdate
     *
     * @return  self
     */
    public function setBirthdate($birthdate)
    {
        $this->birthdate = $birthdate;

        return $this;
    }

    /**
     * Get the value of nationality
     */
    public function getNationality()
    {
        return $this->nationality;
    }

    /**
     * Set the value of nationality
     *
     * @return  self
     */
    public function setNationality($nationality)
    {
        $this->nationality = $nationality;

        return $this;
    }

    /**
     * Get the value of address
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set the value of address
     *
     * @return  self
     */
    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Get the value of zipcode
     */
    public function getZipcode()
    {
        return $this->zipcode;
    }

    /**
     * Set the value of zipcode
     *
     * @return  self
     */
    public function setZipcode($zipcode)
    {
        $this->zipcode = $zipcode;

        return $this;
    }

    /**
     * Get the value of city
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * Set the value of city
     *
     * @return  self
     */
    public function setCity($city)
    {
        $this->city = $city;

        return $this;
    }

    /**
     * Get the value of country
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * Set the value of country
     *
     * @return  self
     */
    public function setCountry($country)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Get the value of professionnalSituation
     */
    public function getProfessionnalSituation()
    {
        return $this->professionnalSituation;
    }

    /**
     * Set the value of professionnalSituation
     *
     * @return  self
     */
    public function setProfessionnalSituation($professionnalSituation)
    {
        $this->professionnalSituation = $professionnalSituation;

        return $this;
    }

    /**
     * Get the value of monthlyIncome
     */
    public function getMonthlyIncome()
    {
        return $this->monthlyIncome;
    }

    /**
     * Set the value of monthlyIncome
     *
     * @return  self
     */
    public function setMonthlyIncome($monthlyIncome)
    {
        $this->monthlyIncome = $monthlyIncome;

        return $this;
    }

    /**
     * Get the value of resetToken
     */
    public function getResetToken()
    {
        return $this->resetToken;
    }

    /**
     * Set the value of resetToken
     *
     * @return  self
     */
    public function setResetToken($resetToken)
    {
        $this->resetToken = $resetToken;

        return $this;
    }

    /**
     * @return Collection<int, Loan>
     */
    public function getLoans(): Collection
    {
        return $this->loans;
    }

    public function addLoan(Loan $loan): static
    {
        if (!$this->loans->contains($loan)) {
            $this->loans->add($loan);
            $loan->setUser($this);
        }

        return $this;
    }

    public function removeLoan(Loan $loan): static
    {
        if ($this->loans->removeElement($loan)) {
            // set the owning side to null (unless already changed)
            if ($loan->getUser() === $this) {
                $loan->setUser(null);
            }
        }

        return $this;
    }

    /**
     * Get the value of idDocument
     */
    public function getIdDocument()
    {
        return $this->idDocument;
    }

    /**
     * Set the value of idDocument
     *
     * @return  self
     */
    public function setIdDocument($idDocument)
    {
        $this->idDocument = $idDocument;

        return $this;
    }

    /**
     * Get the value of idDocumentType
     */
    public function getIdDocumentType()
    {
        return $this->idDocumentType;
    }

    /**
     * Set the value of idDocumentType
     *
     * @return  self
     */
    public function setIdDocumentType($idDocumentType)
    {
        $this->idDocumentType = $idDocumentType;

        return $this;
    }

    /**
     * Get the value of proofOfAddress
     */
    public function getProofOfAddress()
    {
        return $this->proofOfAddress;
    }

    /**
     * Set the value of proofOfAddress
     *
     * @return  self
     */
    public function setProofOfAddress($proofOfAddress)
    {
        $this->proofOfAddress = $proofOfAddress;

        return $this;
    }

    /**
     * Get the value of proofOfAddressType
     */
    public function getProofOfAddressType()
    {
        return $this->proofOfAddressType;
    }

    /**
     * Set the value of proofOfAddressType
     *
     * @return  self
     */
    public function setProofOfAddressType($proofOfAddressType)
    {
        $this->proofOfAddressType = $proofOfAddressType;

        return $this;
    }

    /**
     * Get the value of integrityDocument
     */
    public function getIntegrityDocument()
    {
        return $this->integrityDocument;
    }

    /**
     * Set the value of integrityDocument
     *
     * @return  self
     */
    public function setIntegrityDocument($integrityDocument)
    {
        $this->integrityDocument = $integrityDocument;

        return $this;
    }

    /**
     * Get the value of integrityDocumentType
     */
    public function getIntegrityDocumentType()
    {
        return $this->integrityDocumentType;
    }

    /**
     * Set the value of integrityDocumentType
     *
     * @return  self
     */
    public function setIntegrityDocumentType($integrityDocumentType)
    {
        $this->integrityDocumentType = $integrityDocumentType;

        return $this;
    }

    /**
     * Get the value of verificationStatus
     */
    public function getVerificationStatus()
    {
        return $this->verificationStatus;
    }

    /**
     * Set the value of verificationStatus
     *
     * @return  self
     */
    public function setVerificationStatus($verificationStatus)
    {
        $this->verificationStatus = $verificationStatus;

        return $this;
    }

        /**
     * Get the value of companyEmail
     */
    public function getCompanyEmail(): ?string
    {
        return $this->companyEmail;
    }

    /**
     * Set the value of companyEmail
     *
     * @return  self
     */
    public function setCompanyEmail(?string $companyEmail): self
    {
        $this->companyEmail = $companyEmail;

        return $this;
    }

    /**
     * Get the value of companyTelephone
     */
    public function getCompanyTelephone(): ?string
    {
        return $this->companyTelephone;
    }

    /**
     * Set the value of companyTelephone
     *
     * @return  self
     */
    public function setCompanyTelephone(?string $companyTelephone): self
    {
        $this->companyTelephone = $companyTelephone;

        return $this;
    }

    /**
     * Get the value of companyLegalStatus
     */
    public function getCompanyLegalStatus(): ?string
    {
        return $this->companyLegalStatus;
    }

    /**
     * Set the value of companyLegalStatus
     *
     * @return  self
     */
    public function setCompanyLegalStatus(?string $companyLegalStatus): self
    {
        $this->companyLegalStatus = $companyLegalStatus;

        return $this;
    }

    /**
     * Get the value of companyCity
     */
    public function getCompanyCity(): ?string
    {
        return $this->companyCity;
    }

    /**
     * Set the value of companyCity
     *
     * @return  self
     */
    public function setCompanyCity(?string $companyCity): self
    {
        $this->companyCity = $companyCity;

        return $this;
    }

    /**
     * Get the value of companyCountry
     */
    public function getCompanyCountry(): ?string
    {
        return $this->companyCountry;
    }

    /**
     * Set the value of companyCountry
     *
     * @return  self
     */
    public function setCompanyCountry(?string $companyCountry): self
    {
        $this->companyCountry = $companyCountry;

        return $this;
    }

    /**
     * Get the value of companyZipcode
     */
    public function getCompanyZipcode(): ?string
    {
        return $this->companyZipcode;
    }

    /**
     * Set the value of companyZipcode
     *
     * @return  self
     */
    public function setCompanyZipcode(?string $companyZipcode): self
    {
        $this->companyZipcode = $companyZipcode;

        return $this;
    }

    /**
     * Get the value of companyProfessionalExperience
     */
    public function getCompanyProfessionalExperience(): ?string
    {
        return $this->companyProfessionalExperience;
    }

    /**
     * Set the value of companyProfessionalExperience
     *
     * @return  self
     */
    public function setCompanyProfessionalExperience(?string $companyProfessionalExperience): self
    {
        $this->companyProfessionalExperience = $companyProfessionalExperience;

        return $this;
    }

    /**
     * Get the value of avatar
     */
    public function getAvatar(): ?Media
    {
        return $this->avatar;
    }

    /**
     * Set the value of avatar
     *
     * @return  self
     */
    public function setAvatar(?Media $avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    
}
