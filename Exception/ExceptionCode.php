<?php
namespace phpADAL\Exception;

abstract class ExceptionCode {
 const Unknown = "unknown_error";
 const InvalidArgument = "invalid_argument";
 const AuthenticationFailed = "authentication_failed";
 const AuthenticationCanceled = "authentication_canceled";
 const UnauthorizedResponseExpected = "unauthorized_response_expected";
 const AuthorityNotInValidList = "authority_not_in_valid_list";
}
?>
