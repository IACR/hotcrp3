<?php
// src/pages/iacr/lncswork.php -- saved LNCS editor work
// Part of the IACR extensions; not part of upstream HotCRP.
//
// The LNCS editor (/iacr_lncs_editor) keeps each chair's arrangement of
// papers into volumes and topics as JSON in filestore/lncs_<contactId>.json
// (see README.md in this directory for the format). This is the same file the old
// iacr/*LNCSData.php scripts used, so work saved there carries over.

class IACR_LncsWork {
    /** @var string */
    private $filename;

    /** @param ?string $dir directory for saved work; default `filestore`
     *    in the HotCRP root */
    function __construct(Contact $user, $dir = null) {
        $dir = $dir ?? SiteLoader::$root . "/filestore";
        $this->filename = "{$dir}/lncs_{$user->contactId}.json";
    }

    /** @return string */
    function filename() {
        return $this->filename;
    }

    /** Return the saved JSON, or null if there is none.
     * @return ?string */
    function load() {
        if (!is_file($this->filename)) {
            return null;
        }
        $s = file_get_contents($this->filename);
        return $s === false ? null : $s;
    }

    /** Return the saved work, decoded, or null if there is none or it is
     * not valid.
     * @return ?array<string,mixed> */
    function load_data() {
        $s = $this->load();
        $data = $s === null ? null : json_decode($s, true);
        return is_array($data) ? $data : null;
    }

    /** @param string $json
     * @return bool */
    function save($json) {
        $dir = dirname($this->filename);
        if (!is_dir($dir) && !@mkdir($dir, 0770, true)) {
            return false;
        }
        return file_put_contents($this->filename, $json) !== false;
    }

    /** Delete the saved work. Returns false on failure.
     * @return bool */
    function delete() {
        return !file_exists($this->filename) || @unlink($this->filename);
    }
}
