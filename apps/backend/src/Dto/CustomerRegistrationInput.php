<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validateNameFields')]
final readonly class CustomerRegistrationInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    public string $password;

    #[Assert\Length(max: 100)]
    #[SerializedName('first_name')]
    public ?string $firstName;

    #[Assert\Length(max: 100)]
    #[SerializedName('last_name')]
    public ?string $lastName;

    #[Assert\Length(max: 200)]
    public ?string $name;

    #[Assert\Length(max: 20)]
    #[Assert\Regex('/^\+216[0-9]{8}$/')]
    public ?string $phone;

    public function __construct(
        string $email = '',
        string $password = '',
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $name = null,
        ?string $phone = null,
    ) {
        $this->email = trim($email);
        $this->password = $password;
        $this->firstName = $this->blankToNull($firstName);
        $this->lastName = $this->blankToNull($lastName);
        $this->name = $this->blankToNull($name);
        $this->phone = $this->blankToNull($phone);
    }

    public function validateNameFields(ExecutionContextInterface $context): void
    {
        if (null !== $this->name) {
            return;
        }

        if (null !== $this->firstName && null !== $this->lastName) {
            return;
        }

        if (null === $this->firstName) {
            $context->buildViolation('This value should not be blank.')
                ->atPath('firstName')
                ->addViolation();
        }

        if (null === $this->lastName) {
            $context->buildViolation('This value should not be blank.')
                ->atPath('lastName')
                ->addViolation();
        }
    }

    private function blankToNull(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }
}
