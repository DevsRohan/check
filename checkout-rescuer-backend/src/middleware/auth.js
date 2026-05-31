/**
 * Authentication Middleware
 * Validates API secret key from WordPress plugin
 */

function authMiddleware(req, res, next) {
  const apiKey = req.headers['x-api-key'] || req.query.api_key;
  const secretKey = process.env.API_SECRET_KEY;

  if (!secretKey) {
    // If no secret is configured, allow all (for initial setup)
    return next();
  }

  if (!apiKey || apiKey !== secretKey) {
    return res.status(401).json({
      success: false,
      error: 'Unauthorized. Invalid API key.',
    });
  }

  next();
}

module.exports = authMiddleware;
