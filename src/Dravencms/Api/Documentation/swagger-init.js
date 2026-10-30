(function () {
    "use strict";

    const form = document.getElementById("documentation-auth");
    const tokenInput = document.getElementById("api-token");
    const submitButton = form.querySelector("button");
    const errorMessage = document.getElementById("documentation-error");

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        const token = tokenInput.value.trim();
        if (!token) {
            tokenInput.focus();
            return;
        }
        submitButton.disabled = true;
        errorMessage.hidden = true;

        try {
            const response = await fetch(form.dataset.specificationUrl, {
                headers: { Authorization: "Bearer " + token, Accept: "application/json" },
                cache: "no-store",
                credentials: "omit"
            });
            const specification = await response.json();
            if (!response.ok) {
                throw new Error(specification.message || "Could not load API documentation.");
            }
            const ui = SwaggerUIBundle({
                spec: specification,
                dom_id: "#swagger-ui",
                deepLinking: true,
                validatorUrl: null,
                persistAuthorization: false,
                supportedSubmitMethods: ["get", "head"],
                onComplete: function () {
                    ui.preauthorizeApiKey("BearerAuth", token);
                }
            });
            tokenInput.value = "";
            document.getElementById("documentation-access").hidden = true;
        } catch (error) {
            errorMessage.textContent = error.message || "Could not load API documentation.";
            errorMessage.hidden = false;
        } finally {
            submitButton.disabled = false;
        }
    });
}());
