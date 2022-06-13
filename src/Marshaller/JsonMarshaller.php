<?php

namespace PublicSquare\Marshaller;

use Symfony\Component\Cache\Marshaller\MarshallerInterface;

class JsonMarshaller implements MarshallerInterface
{
    /**
     * {@inheritdoc}
     */
    public function marshall(array $values, ?array &$failed): array
    {
        $serialized = $failed = [];

        foreach ($values as $id => $value) {
            try {
                $serialized[$id] = json_encode($value);

                if ($serialized[$id]) {
                    throw new \Exception((string) json_last_error());
                }
            } catch (\Exception) {
                $failed[] = $id;
            }
        }

        return $serialized;
    }

    /**
     * {@inheritdoc}
     */
    public function unmarshall(string $value)
    {
        $unserializeCallbackHandler = ini_set('unserialize_callback_func', __CLASS__ . '::handleUnserializeCallback');

        try {
            if (false !== $value = json_decode($value, true, flags: JSON_THROW_ON_ERROR)) {
                return $value;
            }

            throw new \DomainException(error_get_last() ? error_get_last()['message'] : 'Failed to unserialize values.');
        } catch (\Exception $e) {
            throw new \ErrorException($e->getMessage(), $e->getCode(), E_ERROR, $e->getFile(), $e->getLine());
        } finally {
            ini_set('unserialize_callback_func', $unserializeCallbackHandler);
        }
    }

    /**
     * @internal
     */
    public static function handleUnserializeCallback(string $class)
    {
        throw new \DomainException('Class not found: ' . $class);
    }
}
