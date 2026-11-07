(function () {
    "use strict";

    SwaggerUIBundle({
        url: document.getElementById("swagger-ui").dataset.specificationUrl,
        dom_id: "#swagger-ui",
        deepLinking: true,
        validatorUrl: null,
        persistAuthorization: false,
        supportedSubmitMethods: ["get", "head"]
    });
}());
