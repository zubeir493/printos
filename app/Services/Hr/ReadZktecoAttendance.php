<?php

namespace App\Services\Hr;

use App\Support\AttendanceDeviceSettings;
use HafizAsifAli\ZkTeco\Data\Punch;
use HafizAsifAli\ZkTeco\Protocol\Decoders\AttendanceDecoder;
use HafizAsifAli\ZkTeco\Protocol\Opcode;
use HafizAsifAli\ZkTeco\ZkDevice;
use RuntimeException;

class ReadZktecoAttendance
{
    /** @return list<array<string, mixed>> */
    public function handle(?array $settings = null): array
    {
        return $this->device($settings)->session(function (ZkDevice $device): array {
            $capacity = $device->rawSession()->sendOrFail(Opcode::ReadCapacity)->payload;

            if (strlen($capacity) < 80) {
                throw new RuntimeException('Unsupported device capacity response. MB360 firmware compatibility must be verified.');
            }

            $records = unpack('Vrecords', substr($capacity, 32, 4))['records'];

            if ($records === 0) {
                return [];
            }

            $buffer = $device->rawSession()->readBuffer(Opcode::ReadAttendance);
            $layout = AttendanceDecoder::detectLayout($buffer, $records);
            $people = $layout === 8 ? $device->users()->all() : [];
            $punches = iterator_to_array(AttendanceDecoder::decode($buffer, $records, $people, $device->config->timezone));

            if ($layout === 8) {
                $numbersBySlot = [];

                foreach ($people as $person) {
                    $numbersBySlot[$person->uid] = $person->userId;
                }

                foreach ($punches as $punch) {
                    if (! isset($numbersBySlot[$punch->uid]) || $numbersBySlot[$punch->uid] !== $punch->userId) {
                        throw new RuntimeException('Legacy attendance references an unresolved device slot. Employee mapping cannot be verified safely.');
                    }
                }
            }

            if (count($punches) < $records) {
                throw new RuntimeException('Incomplete attendance download. The synchronization checkpoint was not advanced.');
            }

            return array_map(
                fn (Punch $punch): array => $punch->toArray(),
                $punches,
            );
        });
    }

    protected function device(?array $settings = null): ZkDevice
    {
        if (! config('attendance_device.network_enabled')) {
            throw new RuntimeException('Device access is disabled on this server. Enable it after configuring the private network route.');
        }

        $settings ??= AttendanceDeviceSettings::all();

        return ZkDevice::fromArray($settings, $settings['id']);
    }

    public function testConnection(): void
    {
        $this->device()->session(fn (ZkDevice $device) => $device->rawSession()->sendOrFail(Opcode::ReadCapacity));
    }

    /** @return list<array{user_id: string, uid: int, name: string}> */
    public function users(): array
    {
        return $this->device()->session(function (ZkDevice $device): array {
            $users = [];

            foreach ($device->users()->all() as $person) {
                $users[] = ['user_id' => $person->userId, 'uid' => $person->uid, 'name' => $person->name];
            }

            return $users;
        });
    }
}
