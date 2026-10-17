import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/constants/api_constants.dart';
import '../../../core/theme/app_colors.dart';
import '../../../providers/auth_provider.dart';

class ServerSettingsSheet extends StatefulWidget {
  const ServerSettingsSheet({
    super.key,
    required this.authProvider,
    this.allowInsecure = kDebugMode,
  });

  final AuthProvider authProvider;
  final bool allowInsecure;

  static Future<String?> show(
    BuildContext context, {
    required AuthProvider authProvider,
    bool allowInsecure = kDebugMode,
  }) {
    return showDialog<String>(
      context: context,
      builder: (_) => ServerSettingsSheet(
        authProvider: authProvider,
        allowInsecure: allowInsecure,
      ),
    );
  }

  @override
  State<ServerSettingsSheet> createState() => _ServerSettingsSheetState();
}

class _ServerSettingsSheetState extends State<ServerSettingsSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _urlController;

  @override
  void initState() {
    super.initState();
    _urlController = TextEditingController(
      text: widget.authProvider.baseUrl,
    );
  }

  @override
  void dispose() {
    _urlController.dispose();
    super.dispose();
  }

  String? _validateUrl(String? value) {
    final url = value?.trim() ?? '';
    final uri = Uri.tryParse(url);

    if (uri == null || !uri.isAbsolute || uri.host.isEmpty) {
      return 'عنوان الخادم غير صالح';
    }

    if (!widget.allowInsecure && uri.scheme.toLowerCase() != 'https') {
      return 'يجب أن يبدأ عنوان الخادم بـ https://';
    }

    return null;
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    final newUrl = _urlController.text.trim();
    await widget.authProvider.updateBaseUrl(newUrl);

    if (mounted) {
      Navigator.of(context).pop(newUrl);
    }
  }

  @override
  Widget build(BuildContext context) {
    final localHint = widget.allowInsecure
        ? 'http://127.0.0.1:8000/api'
        : 'https://example.com/api';

    return AlertDialog(
      backgroundColor: AppColors.elevatedSurface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: AppColors.strokeBorder),
      ),
      title: Row(
        children: [
          const Icon(Icons.dns_outlined, color: AppColors.goldAccent),
          const SizedBox(width: 8),
          Text(
            'إعدادات السيرفر (API)',
            style: GoogleFonts.cairo(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
        ],
      ),
      content: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'عنوان الاتصال بالـ Backend:',
                style: GoogleFonts.cairo(
                  fontSize: 12,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _urlController,
                textDirection: TextDirection.ltr,
                textAlign: TextAlign.left,
                style: GoogleFonts.outfit(
                  color: AppColors.textPrimary,
                  fontSize: 13,
                ),
                decoration: InputDecoration(
                  hintText: localHint,
                  prefixIcon: const Icon(
                    Icons.link,
                    color: AppColors.textMuted,
                    size: 20,
                  ),
                ),
                validator: _validateUrl,
              ),
              const SizedBox(height: 16),
              Text(
                'خيارات سريعة:',
                style: GoogleFonts.cairo(
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textMuted,
                ),
              ),
              const SizedBox(height: 6),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  if (widget.allowInsecure)
                    ActionChip(
                      label: Text(
                        'محلي (Web / PC)',
                        style: GoogleFonts.cairo(
                          fontSize: 11,
                          color: Colors.white,
                        ),
                      ),
                      backgroundColor: AppColors.surface,
                      onPressed: () {
                        _urlController.text = 'http://127.0.0.1:8000/api';
                      },
                    ),
                  if (widget.allowInsecure)
                    ActionChip(
                      label: Text(
                        'محاكي أندرويد',
                        style: GoogleFonts.cairo(
                          fontSize: 11,
                          color: Colors.white,
                        ),
                      ),
                      backgroundColor: AppColors.surface,
                      onPressed: () {
                        _urlController.text = 'http://10.0.2.2:8000/api';
                      },
                    ),
                  ActionChip(
                    label: Text(
                      'السيرفر الحي',
                      style: GoogleFonts.cairo(
                        fontSize: 11,
                        color: AppColors.goldAccent,
                      ),
                    ),
                    backgroundColor: AppColors.surface,
                    onPressed: () {
                      _urlController.text = ApiConstants.liveProductionUrl;
                    },
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: Text(
            'إلغاء',
            style: GoogleFonts.cairo(color: AppColors.textMuted),
          ),
        ),
        ElevatedButton(
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.primary,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
          ),
          onPressed: _save,
          child: Text(
            'حفظ وتطبيق',
            style: GoogleFonts.cairo(
              color: Colors.white,
              fontWeight: FontWeight.bold,
            ),
          ),
        ),
      ],
    );
  }
}
