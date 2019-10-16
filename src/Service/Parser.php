<?php

namespace AVKluchko\X509Bundle\Service;

use AVKluchko\GovernmentBundle\Validator\PSRNValidator;

class Parser
{
    public const SUBJECT_COMPANY = 'company';
    public const SUBJECT_OFFICIAL = 'official';
    public const SUBJECT_PERSON = 'person';

    private $reader;

    public function __construct(CertificateReader $reader)
    {
        $this->reader = $reader;
    }

    public function parse(string $filename): array
    {
        $data = $this->reader->loadData($filename);


//        $validTo = new \DateTime(strtotime($data['validTo']));
//        var_dump($validTo);
//
//        $validFromTime = new \DateTime(strtotime($data['validFrom_time_t']));
//        var_dump($validFromTime);
//        $validToTime = new \DateTime(strtotime($data['validTo_time_t']));
//        var_dump($validToTime);

        return [
            'data' => $data,
            'fingerprint' => $data['fingerprint'],
            'validFrom' => new \DateTime(date('Y-m-d H:i:s', $data['validFrom_time_t'])),
            'validTo' => new \DateTime(date('Y-m-d H:i:s', $data['validTo_time_t'])),
            'signTool' => isset($data['extensions']) ?
                $this->parseSignTool($data['extensions']) : null,

            'subject' => $this->parseSubject($data['subject']),
            'issuer' => $this->parseIssuer($data['issuer']),
        ];
    }

    private function parseSubject(array $data): array
    {
        $OGRN = $this->parsePSRN($data);

        $type = self::SUBJECT_COMPANY;
        if (isset($data['givenName'])) {
            $type = $OGRN ? self::SUBJECT_OFFICIAL : self::SUBJECT_PERSON;
        }

        $personMiddleName = null;
        $personName = null;

        if (isset($data['givenName'])) {
            [$personName, $personMiddleName] = explode(' ', $data['givenName']);
        }

        return [
            'type' => $type,
            'shortName' => $data['commonName'],
            'company' => $data['organizationName'],
            'title' => $data['title'] ?? null,
            'country' => $data['countryName'],
            'state' => $data['stateOrProvinceName'],
            'locality' => $data['localityName'],
            'address' => $data['streetAddress'] ?? null,
            'email' => isset($data['emailAddress']) ?
                $this->parseEmail($data['emailAddress']) : null,
            'OGRN' => $OGRN,
            'INN' => $data['INN'] ?? null,
            'surname' => $data['surname'] ?? null,
            'name' => $personName,
            'middleName' => $personMiddleName,
            'SNILS' => $data['SNILS'] ?? null,
        ];
    }

    private function parseEmail($email): string
    {
        if (is_array($email)) {
            return $email[count($email) - 1];
        }

        return $email;
    }

    private function parseIssuer(array $data): array
    {
        return [
            'name' => $data['organizationName'],
            'shortName' => $data['commonName'],
            'unitName' => $data['organizationalUnitName'] ?? null,
            'country' => $data['countryName'],
            'state' => $data['stateOrProvinceName'] ?? null,
            'locality' => $data['localityName'],
            'address' => $data['streetAddress'] ?? null,
            'email' => $data['emailAddress'] ?? null,
            'OGRN' => $data['OGRN'] ?? null,
            'INN' => $data['INN'] ?? null,
        ];
    }

    private function parseSignTool(array $data): ?string
    {
        $signTool = $data['subjectSignTool'] ??
            $data['1.2.643.100.111'] ?? null;

        if (!$signTool) {
            return null;
        }

        return trim($signTool, " +\x00..\x1F");
    }

    private function parsePSRN(array $data): ?string
    {
        // if use OpenSSL 1.1
        if (isset($data['OGRN'])) {
            return $data['OGRN'];
        }

        // if use older OpenSSL 1.0
        if (isset($data['undefined'])) {
            $validator = new PSRNValidator();

            foreach ($data['undefined'] as $value) {
                if ($validator->isValid($value)) {
                    return $value;
                }
            }
        }

        return null;
    }
}