<?php
include '../vendor/autoload.php';
include '${cls_name}.php';
include 'GPBMetadata/Runtime/Protobuf/${cls_name}.php';

$typeArr = array("double", "float", "int32", "int64", "uint32", "uint64", "sint32", "sint64",
    "fixed32", "fixed64", "sfixed32", "sfixed64", "bool", "string", "bytes");

$inst = new ${cls_name}();

printSep("set properties");

$reflect = new ReflectionObject($inst);
$methods = $reflect->getMethods();
foreach ($methods as $key => $value) {
    $setMethodName = $value->getName();
    $found = strpos($setMethodName, 'set');
    if ($found !== false) {
        $repeated = false;
        $setMethodParamCls = "";

        parserFieldPropsFromComments($value, $repeated, $setMethodParamCls);
        setFieldDefaultValue($inst, $repeated, $setMethodName, $setMethodParamCls);
    }
}

$data = $inst->serializeToString();
file_put_contents('data.bin', $data);

$data = file_get_contents('data.bin');
$decode = new Person();
$decode->mergeFromString($data);

printSep("print object");
$prefix = 0;
printObj($decode, $prefix);

function setFieldDefaultValue(&$parentObj, $repeated, $setMethodName, $setMethodParamCls)
{
    $parentReflect = new ReflectionObject($parentObj);
    $parentObjType = $parentReflect->getName();
    echo "object type      = $parentObjType\n";
    echo "field type       = $setMethodParamCls\n";
    echo "field method     = $setMethodName\n";
    echo "field repeated   = $repeated\n\n";

    if (isStandType($setMethodParamCls)) { //
        $defaultVal = getDefaultValByType($setMethodParamCls, $repeated);
        call_user_func(array($parentObj, $setMethodName), $defaultVal);

        return;
    }

    require "./$setMethodParamCls.php";

    $childObj = new $setMethodParamCls();
    $childReflect = new ReflectionObject($childObj);
    $childMethods = $childReflect->getMethods();
    $isEnum = true;
    foreach ($childMethods as $key => $value) {
        $childSetMethodName = $value->getName();
        $found = strpos($childSetMethodName, 'set');
        if ($found === false) {
            continue;
        }

        $isEnum = false; // enum has no set method

        $childRepeated = 0;
        $childSetMethodParamCls = "";
        parserFieldPropsFromComments($value, $childRepeated, $childSetMethodParamCls);
        setFieldDefaultValue($childObj, $childRepeated, $childSetMethodName, $childSetMethodParamCls);
    }

    if ($isEnum) return;

    $children = $repeated? array($childObj) : $childObj;
    call_user_func(array($parentObj, $setMethodName), $children);
}

function parserFieldPropsFromComments($value, &$repeated, &$className)
{
    $comments = $value->getDocComment();
    // <code>.Address address = 4;</code>
    $pattern = '/<code>(repeated\s)?\.?(.+?)\s/is';
    preg_match($pattern, $comments, $match);
    if (sizeof($match) >= 3) {
        $repeated = $match[1];
        $className = $match[2];
    } else if (sizeof($match) >= 2) {
        $repeated = false;
        $className = $match[1];
    }
    $repeated = trim($repeated);
    if ($repeated === 'repeated')
        $repeated = 1;
    else
        $repeated = 0;

    $className = trim($className);
}

function getDefaultValByType($type, $repeat)
{
    if (!$repeat) {
        return getRandValByType($type);
    }

    $count = rand(3, 30);
    $ret = array();
    for ($i = 0; $i < $count; $i++) {
        $item = getRandValByType($type);
        $ret[$i] = $item;
    }

    return $ret;
}

function getRandValByType($type)
{
    if ($type === 'bool') { // : string
        $ret = rand(0, 1);
        return $ret;

    } else if ($type === 'string') { // : string
        $r = rand(3, 100);
        $ret = getRandStr($r);
        return $ret;

    } else if ($type === 'float') { // java float: float
        $start = pow(2, 7) * -1;
        $end = pow(2, 7) - 1;

        $ret = getRandFloat($start, $end);
        return $ret;

    } else if ($type === 'double') { // java double: float
        $start = pow(2, 10) * -1;
        $end = pow(2, 10) - 1;

        $ret = getRandFloat($start, $end);
        return $ret;

    } else if ($type === 'int32' || $type === 'sint32' || $type === 'sfixed32') { // go int32 : integer
        $start = pow(2, 31) * -1;
        $end = pow(2, 31) - 1; // 2147483647

        $ret = rand($start, $end);
        return $ret;

    } else if ($type === 'uint32' || $type === 'fixed32') { // go uint32 : integer
        $end = pow(2, 32) - 1; // 4294967295

        $ret = rand(0, $end);
        return $ret;

    } else if ($type === 'int64' || $type === 'sint64' || $type === 'sfixed64') { // go int64 : integer/string
        $ret = randUint64('9223372036854775807');

        $sign = rand(0, 1);
        if ($sign == 0) {
            $ret *= -1;
        }
        return $ret;

    } else if ($type === 'uint64' || $type === 'fixed64') { // go uint64 : integer
        $ret = randUint64('18446744073709551615');
        return $ret;

    }
}

function printObj($obj, $prefix)
{
    $reflect = new ReflectionObject($obj);
    $name = $reflect->getName();
    echo str_repeat(' ', $prefix) . "[$name]\n";

    if ($name === 'Google\Protobuf\Internal\RepeatedField') {
        foreach ($obj as $key => $value) {
            printObj($value, $prefix + 3);
        }
    }
    if ($name)

        $methods = $reflect->getMethods();
    foreach ($methods as $key => $value) {
        $methodName = $value->getName();
        $found = strpos($methodName, 'get');
        if ($found !== false && $methodName !== "getClass" && $methodName !== "getType" && $methodName !== "getLegacyClass"
            && $methodName !== "getIterator") {
            if ($methodName === 'getAddressItems') {
                echo '';
            }

            $repeated = false;
            $className = "";
            parserFieldPropsFromComments($value, $repeated, $className);
            $var = call_user_func(array($obj, $methodName));

            if (isStandType($className) || gettype($var) === 'integer') {
                printField($methodName, $var, $repeated, $className, $prefix + 3);
                continue;
            }

            $name = str_repeat(' ', $prefix + 3) . substr($methodName, 3);
            $name = str_pad($name, 26, " ", STR_PAD_RIGHT);
            echo "$name\n";
            printObj($var, $prefix + 3);
        }
    }
}

function printField($methodName, $var, $repeated, $className, $prefix)
{
    if ($className === 'bool') {
        if ($var) $var = 'true';
        else $var = 'false';
    }

    if (!$repeated) {
        $name = str_repeat(' ', $prefix) . substr($methodName, 3);
        $name = str_pad($name, 26, " ", STR_PAD_RIGHT);
        echo "$name = $var\n";
        return;
    }

    $arr = array();
    foreach ($var as $key => $value) {
        $arr[] = $value;
    }

    $name = str_pad(str_repeat(' ', $prefix) . substr($methodName, 3), 26, " ", STR_PAD_RIGHT);
    echo "$name = [" . join(",", $arr) . "] \n";
}

function isStandType($className)
{
    global $typeArr;

    return in_array($className, $typeArr);
}

function getRandStr($length = 10)
{
    srand(date("s"));
    $chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $string = "";
    while (strlen($string) < $length) {
        $string .= substr($chars, (rand() % (strlen($chars))), 1);
    }
    return ($string);
}

function randUint64($maxStrValue){
    $result = '';
    $maxBegin = '';

    for($i = 0; $i < strlen($maxStrValue); $i++){
        $maxDigit = $maxStrValue[$i];

        if($result === $maxBegin){
            $result .= random_int(0, $maxDigit);
        }else{
            $result .= random_int(0, 9);
        }
        $maxBegin .= $maxDigit;
    }

    $result = '1' . substr($result,1);
    return intval($result);
}

function getRandFloat($min = 0, $max = 1)
{
    $rl = mt_rand() / mt_getrandmax();
    return ($min + ($rl * ($max - $min)));
}

function printSep($title)
{
    echo(str_repeat("=", 16) . " " . $title . " " . str_repeat("=", 16) . "\n");
}
