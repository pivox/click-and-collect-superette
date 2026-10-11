<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class AdminSubscriptionPricingInput
{
    #[SerializedName('merchant_id')]
    #[Assert\Uuid]
    public ?string $merchantId = null;

    #[SerializedName('billing_interval')]
    #[Assert\NotBlank]
    #[Assert\Choice(['monthly', 'annual'])]
    public string $billingInterval = '';

    #[SerializedName('pricing_mode')]
    #[Assert\NotBlank]
    #[Assert\Choice(['paid', 'discounted', 'free'])]
    public string $pricingMode = '';

    #[SerializedName('base_price_tnd')]
    #[Assert\NotBlank]
    #[Assert\Regex('/^\d{1,7}(?:\.\d{1,3})?$/D')]
    public string $basePriceTnd = '';

    #[SerializedName('free_months')]
    #[Assert\Range(min: 1, max: 120)]
    public ?int $freeMonths = null;

    /** @var list<array<string, mixed>> */
    #[Assert\Count(max: 120)]
    public array $discounts = [];

    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 1000)]
    public string $reason = '';

    #[Assert\Callback]
    public function validatePricing(ExecutionContextInterface $context): void
    {
        if ('free' !== $this->pricingMode && null !== $this->freeMonths) {
            $context->buildViolation('La durée de gratuité est réservée au mode gratuit.')->atPath('freeMonths')->addViolation();
        }
        if (('discounted' === $this->pricingMode) !== ([] !== $this->discounts)) {
            $context->buildViolation('Les remises sont obligatoires uniquement pour le mode avec remise.')->atPath('discounts')->addViolation();
        }
        if (!array_is_list($this->discounts)) {
            $context->buildViolation('Les remises doivent être une liste.')->atPath('discounts')->addViolation();
        }
        $occupied = [];
        foreach ($this->discounts as $index => $rule) {
            if (!\is_array($rule)
                || !isset($rule['from_month'], $rule['to_month'], $rule['type'], $rule['value'])
                || !\is_int($rule['from_month']) || !\is_int($rule['to_month'])
                || $rule['from_month'] < 1 || $rule['to_month'] > 120 || $rule['to_month'] < $rule['from_month']
                || !\in_array($rule['type'], ['fixed', 'percentage'], true)
                || !\is_string($rule['value']) || !preg_match('/^\d{1,7}(?:\.\d{1,3})?$/D', $rule['value'])
                || bccomp($rule['value'], '0', 3) <= 0
                || ('percentage' === $rule['type'] && bccomp($rule['value'], '100', 3) > 0)
            ) {
                $context->buildViolation('Remise invalide : mois 1 à 120, montant positif, pourcentage maximal 100.')->atPath('discounts['.$index.']')->addViolation();
                continue;
            }
            foreach (range($rule['from_month'], $rule['to_month']) as $month) {
                if (isset($occupied[$month])) {
                    $context->buildViolation('Les périodes de remise ne doivent pas se chevaucher.')->atPath('discounts')->addViolation();
                    break;
                }
                $occupied[$month] = true;
            }
        }
    }

    /** @return array<string, mixed> */
    public function terms(\DateTimeImmutable $effectiveAt): array
    {
        return [
            'billing_interval' => $this->billingInterval,
            'pricing_mode' => $this->pricingMode,
            'base_price_tnd' => bcadd($this->basePriceTnd, '0', 3),
            'free_months' => $this->freeMonths,
            'discounts' => array_map(static fn (array $rule): array => [
                'from_month' => $rule['from_month'],
                'to_month' => $rule['to_month'],
                'type' => $rule['type'],
                'value' => bcadd($rule['value'], '0', 3),
            ], $this->discounts),
            'effective_at' => $effectiveAt->format(\DATE_ATOM),
        ];
    }
}
