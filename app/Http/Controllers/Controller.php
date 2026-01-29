<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="BlueSky API",
 *     version="2.0.0",
 *     description="Laravel-based API for the BTD6 Maplist project"
 * )
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_SERVER,
 *     description="API Server"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter Discord bearer token"
 * )
 */
abstract class Controller
{
    //
}
