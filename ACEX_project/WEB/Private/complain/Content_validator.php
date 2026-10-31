<?php

namespace ACEX_project\WEB\Private\complain;

/**
 * Validate if the content have violate our policity or rules, or is inaproppriate
 *  * NOT IMPLEMENTED
 * @param null|string $reject_reason the reason why the text is reject will be written here 
 * @param null|int $reject_type e.g. inapropriate content.
 * * 1 = violate policies or rules(general)
 * * 2 = inapropriate content(general)
 */
function Validate_content(string $target, ?string &$reject_reason, ?int &$reject_type) : bool{
    return true;
}