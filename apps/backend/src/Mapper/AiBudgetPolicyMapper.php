<?php

declare(strict_types=1);

namespace App\Mapper;

use App\ApiResource\AiBudgetPolicyOutput;
use App\Dto\AiBudgetPolicyWriteInput;
use App\Entity\AiBudgetPolicy;

final readonly class AiBudgetPolicyMapper
{
    public function toOutput(AiBudgetPolicy $policy): AiBudgetPolicyOutput
    {
        return new AiBudgetPolicyOutput(
            enabled: $policy->isEnabled(),
            openaiEnabled: $policy->isOpenaiEnabled(),
            geminiEnabled: $policy->isGeminiEnabled(),
            mistralEnabled: $policy->isMistralEnabled(),
            qwenEnabled: $policy->isQwenEnabled(),
            currency: $policy->getCurrency(),
            globalPeriodDays: $policy->getGlobalPeriodDays(),
            alertThresholdPercent: $policy->getAlertThresholdPercent(),
            maxCostPerCallAmount: $policy->getMaxCostPerCallAmount(),
            maxCostPerPhotoAmount: $policy->getMaxCostPerPhotoAmount(),
            maxCostPerShopAmount: $policy->getMaxCostPerShopAmount(),
            globalPeriodCapAmount: $policy->getGlobalPeriodCapAmount(),
            maxOutputTokensPerCall: $policy->getMaxOutputTokensPerCall(),
            maxCropsPerPhoto: $policy->getMaxCropsPerPhoto(),
            maxAttemptsPerPhoto: $policy->getMaxAttemptsPerPhoto(),
            maxCallDurationSeconds: $policy->getMaxCallDurationSeconds(),
            maxConcurrentCalls: $policy->getMaxConcurrentCalls(),
            updatedAt: $policy->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    public function applyWriteInput(AiBudgetPolicy $policy, AiBudgetPolicyWriteInput $input): AiBudgetPolicy
    {
        return $policy
            ->setEnabled($input->enabled)
            ->setOpenaiEnabled($input->openaiEnabled)
            ->setGeminiEnabled($input->geminiEnabled)
            ->setMistralEnabled($input->mistralEnabled)
            ->setQwenEnabled($input->qwenEnabled)
            ->setCurrency($input->currency)
            ->setGlobalPeriodDays($input->globalPeriodDays)
            ->setAlertThresholdPercent($input->alertThresholdPercent)
            ->setMaxCostPerCallAmount($input->maxCostPerCallAmount)
            ->setMaxCostPerPhotoAmount($input->maxCostPerPhotoAmount)
            ->setMaxCostPerShopAmount($input->maxCostPerShopAmount)
            ->setGlobalPeriodCapAmount($input->globalPeriodCapAmount)
            ->setMaxOutputTokensPerCall($input->maxOutputTokensPerCall)
            ->setMaxCropsPerPhoto($input->maxCropsPerPhoto)
            ->setMaxAttemptsPerPhoto($input->maxAttemptsPerPhoto)
            ->setMaxCallDurationSeconds($input->maxCallDurationSeconds)
            ->setMaxConcurrentCalls($input->maxConcurrentCalls);
    }
}
