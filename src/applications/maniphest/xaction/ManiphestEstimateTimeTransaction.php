<?php

class ManiphestEstimateTimeTransaction extends ManiphestTaskTransactionType
{
    const TRANSACTIONTYPE = 'estimate';

    public function generateOldValue($object)
    {
        return $object->getEstimate();
    }

    public function applyInternalEffects($object, $value)
    {
        $object->setEstimate($value);
    }

    public function getActionStrength()
    {
        return 1.4;
    }

    public function getActionName()
    {
        $old = $this->getOldValue();

        if (!strlen($old)) {
            return pht('Created');
        }

        return pht('Changed');
    }

    public function getEstimate()
    {
        $old = $this->getOldValue();

        if (!strlen($old)) {
            return pht(
                '%s set estimate to this task.',
                $this->renderAuthor()
            );
        }

        return pht(
            '%s changed estimate from %s to %s.',
            $this->renderAuthor(),
            $this->renderOldValue(),
            $this->renderNewValue()
        );
    }

    public function validateTransactions($object, array $xactions)
    {
        $errors = array();
        $value = '';

        foreach ($xactions as $xaction) {
            $value = $xaction->getNewValue();
        }

        if ($value && !$this->isTimeFormatCorrect($value)) {
            $errors[] = $this->newRequiredError(pht(
                'Invalid estimate time format. Allowed format is: 1w 4d 2h 30m. ' .
                'You can specify any those modificators at any order.'
            ));
        }

        return $errors;
    }

    /**
     * @param string $time
     *
     * @return bool
     */
    protected function isTimeFormatCorrect(string $time): bool
    {
        $parts = explode(' ', $time);

        foreach ($parts as $part) {
            $modifier = substr($part, -1, 1);
            $value = substr($part, 0, strlen($part) -1);

            if (!is_numeric($value)) {
                return false;
            }

            switch ($modifier) {
                case 'w':
                case 'd':
                    break;

                case 'h':
                    if ($value > 23) {
                        return false;
                    }
                    break;

                case 'm':
                    if ($value > 60) {
                        return false;
                    }
                    break;

                default:
                    return false;
            }
        }

        return true;
    }
}
